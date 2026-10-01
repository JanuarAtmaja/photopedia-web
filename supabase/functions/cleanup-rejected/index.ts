import { createClient } from "@supabase/supabase-js";

const jsonHeaders = {
  "Content-Type": "application/json",
};

Deno.serve(async (req) => {
  try {
    const supabaseUrl = Deno.env.get("SUPABASE_URL");
    const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

    if (!supabaseUrl || !serviceRoleKey) {
      return new Response(
        JSON.stringify({
          success: false,
          error: "SUPABASE_URL atau SUPABASE_SERVICE_ROLE_KEY belum di-set",
        }),
        { status: 500, headers: jsonHeaders }
      );
    }

    const supabase = createClient(supabaseUrl, serviceRoleKey, {
      auth: {
        persistSession: false,
        autoRefreshToken: false,
      },
    });

    const cutoff = new Date(Date.now() - 12 * 60 * 60 * 1000).toISOString();

    const { data: expiredRows, error: fetchErr } = await supabase
      .from("frame_submissions")
      .select("id, filename")
      .eq("status", "rejected")
      .lt("reviewed_at", cutoff);

    if (fetchErr) {
      throw fetchErr;
    }

    if (!expiredRows || expiredRows.length === 0) {
      return new Response(
        JSON.stringify({
          success: true,
          deleted: 0,
          message: "Tidak ada rejected submission yang lewat 12 jam",
        }),
        { status: 200, headers: jsonHeaders }
      );
    }

    let deletedCount = 0;
    const errors: string[] = [];

    for (const row of expiredRows) {
      const filename = row.filename;

      if (filename) {
        const { error: storageErr } = await supabase.storage
          .from("submissions")
          .remove([filename]);

        if (storageErr) {
          const message = String(storageErr.message || "storage remove failed");
          if (!message.toLowerCase().includes("not found")) {
            errors.push(`storage:${filename}:${message}`);
          }
        }
      }

      const { error: deleteErr } = await supabase
        .from("frame_submissions")
        .delete()
        .eq("id", row.id);

      if (deleteErr) {
        errors.push(`db:${row.id}:${deleteErr.message}`);
      } else {
        deletedCount++;
      }
    }

    return new Response(
      JSON.stringify({
        success: true,
        deleted: deletedCount,
        errors,
      }),
      { status: 200, headers: jsonHeaders }
    );
  } catch (error) {
    const message = error instanceof Error ? error.message : "Unknown error";

    return new Response(
      JSON.stringify({
        success: false,
        error: message,
      }),
      { status: 500, headers: jsonHeaders }
    );
  }
});
