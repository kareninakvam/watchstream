[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$sourceRoot = Split-Path $PSScriptRoot -Parent
$testId = 'watchstream-us01-' + (Get-Date -Format 'yyyyMMddHHmmss')
$targetRoot = Join-Path ([IO.Path]::GetTempPath()) $testId
if (Test-Path -LiteralPath $targetRoot) { throw 'O destino ja existe; nenhum arquivo foi alterado.' }
if (-not (Test-Path -LiteralPath (Join-Path $sourceRoot 'composer.lock')) -or -not (Test-Path -LiteralPath (Join-Path $sourceRoot 'package-lock.json'))) {
    throw 'Os dois lockfiles sao obrigatorios para este teste.'
}
$dockerCmd = Get-Command docker -ErrorAction SilentlyContinue
if ($dockerCmd) { $dockerExe = $dockerCmd.Source }
else { $dockerExe = Join-Path $env:ProgramFiles 'Docker\Docker\resources\bin\docker.exe' }
& $dockerExe info --format '{{.OSType}}'
if ($LASTEXITCODE -ne 0) { throw 'Docker inacessivel. Nenhuma copia iniciada.' }
New-Item -ItemType Directory -Path $targetRoot | Out-Null
$excludedDirs = @('.git','vendor','node_modules',
    (Join-Path $sourceRoot 'public\build'),
    (Join-Path $sourceRoot 'bootstrap\cache'),
    (Join-Path $sourceRoot 'storage\framework'),
    (Join-Path $sourceRoot 'storage\logs'))
& robocopy $sourceRoot $targetRoot /E /XJ /NFL /NDL /NJH /NJS /NP /XD $excludedDirs /XF '.env' '.env.*' 'hot' '*.log'
if ($LASTEXITCODE -gt 7) { throw ('Falha ao copiar: robocopy ' + $LASTEXITCODE) }
Copy-Item -LiteralPath (Join-Path $sourceRoot '.env.example') -Destination (Join-Path $targetRoot '.env.example')
$example = [IO.File]::ReadAllText((Join-Path $targetRoot '.env.example'))
$example = $example.Replace('APP_PORT=8080','APP_PORT=8081').Replace('APP_URL=http://localhost:8080','APP_URL=http://localhost:8081')
[IO.File]::WriteAllText((Join-Path $targetRoot '.env'),$example,[Text.UTF8Encoding]::new($false))
$previousProject = $env:COMPOSE_PROJECT_NAME
$previousDirectory = Get-Location
try {
    # A precedencia dessa variavel sobre name: isola containers, rede e volumes.
    $env:COMPOSE_PROJECT_NAME = $testId
    Set-Location $targetRoot
    & (Join-Path $targetRoot 'scripts\setup.ps1')
    & $dockerExe compose exec -T web php artisan test
    if ($LASTEXITCODE -ne 0) { throw 'Testes Laravel falharam.' }
    & $dockerExe compose exec -T web vendor/bin/pint --test
    if ($LASTEXITCODE -ne 0) { throw 'Analise de estilo falhou.' }
    & $dockerExe compose exec -T web php artisan horizon:status
    if ($LASTEXITCODE -ne 0) { throw 'Horizon nao esta ativo.' }
    $health = Invoke-WebRequest -UseBasicParsing 'http://localhost:8081/up'
    $homeResponse = Invoke-WebRequest -UseBasicParsing 'http://localhost:8081/'
    Write-Host ('HTTP /up: ' + $health.StatusCode + '; Home: ' + $homeResponse.StatusCode)
    & $dockerExe compose ps
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao consultar servicos.' }
    Write-Host 'Validacao automatizada da copia limpa concluida.'
} finally {
    Set-Location $previousDirectory
    $env:COMPOSE_PROJECT_NAME = $previousProject
    Write-Host ('Pasta de teste: ' + $targetRoot)
    Write-Host ('Projeto Compose isolado: ' + $testId)
    Write-Host ('Para parar somente o teste: docker compose -p ' + $testId + ' -f "' + (Join-Path $targetRoot 'compose.yaml') + '" --env-file "' + (Join-Path $targetRoot '.env') + '" down')
}
