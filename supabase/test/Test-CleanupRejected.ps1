$ErrorActionPreference = 'Stop'

$SupabaseUrl = $env:SUPABASE_URL
$ServiceRoleKey = $env:SUPABASE_SERVICE_ROLE_KEY
$FunctionName = if ($env:CLEANUP_FUNCTION_NAME) { $env:CLEANUP_FUNCTION_NAME } else { 'cleanup-rejected' }

if (-not $SupabaseUrl) {
    throw "SUPABASE_URL not set. Example: `$env:SUPABASE_URL='https://<project-ref>.supabase.co'"
}

if (-not $ServiceRoleKey) {
    throw "SUPABASE_SERVICE_ROLE_KEY not set."
}

$uri = "$($SupabaseUrl.TrimEnd('/'))/functions/v1/$FunctionName"

Write-Host "Testing cleanup function: $uri"

$headers = @{
    Authorization = "Bearer $ServiceRoleKey"
    'Content-Type' = 'application/json'
}

try {
    $response = Invoke-RestMethod -Method Post -Uri $uri -Headers $headers -Body '{}'
    $response | ConvertTo-Json -Depth 10
}
catch {
    Write-Error $_.Exception.Message
    exit 1
}

Write-Host ""
Write-Host "Optional SQL to check cron status in Supabase:"
Write-Host "select * from cron.job;"
Write-Host "select cron.unschedule('cleanup-rejected-submissions'); -- jika ingin hapus scheduler"
