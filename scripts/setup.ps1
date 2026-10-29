[CmdletBinding()]
param([switch]$Frontend)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$dockerCommand = Get-Command docker -ErrorAction SilentlyContinue
if ($dockerCommand) { $script:Docker = $dockerCommand.Source }
else { $script:Docker = Join-Path $env:ProgramFiles 'Docker\Docker\resources\bin\docker.exe' }
if (-not (Test-Path -LiteralPath $script:Docker)) { throw 'Docker Desktop nao encontrado.' }
function Invoke-Docker {
    & $script:Docker @args
    if ($LASTEXITCODE -ne 0) { throw ('Docker falhou: ' + ($args -join ' ')) }
}
# Verificar o motor antes de alterar arquivos ou iniciar servicos.
Invoke-Docker info --format '{{.OSType}}'
if (-not (Test-Path -LiteralPath .env)) { Copy-Item .env.example .env }
Invoke-Docker compose config --quiet
Invoke-Docker compose build web
Invoke-Docker compose up -d --wait mysql redis
if (Test-Path -LiteralPath composer.lock) {
    Invoke-Docker compose run --rm --no-deps web composer install --no-interaction --prefer-dist
} else {
    Invoke-Docker compose run --rm --no-deps web composer update --no-interaction --prefer-dist
}
# Migrations e configuracao Pulse ja pertencem ao codigo-fonte.
# Republicar o provider gera outro timestamp e duplica a criacao das tabelas.
$pulseMigrations = @(Get-ChildItem -LiteralPath database/migrations -Filter '*create_pulse_tables.php')
if ($pulseMigrations.Count -ne 1) {
    throw 'Esperada exatamente uma migration Pulse no codigo-fonte. Revise antes de migrar; nenhum banco sera apagado.'
}
$envContent = [IO.File]::ReadAllText((Join-Path (Get-Location) '.env'))
if ($envContent -notmatch '(?m)^APP_KEY=.+') {
    Invoke-Docker compose run --rm --no-deps web php artisan key:generate --no-interaction
}
Invoke-Docker compose run --rm --no-deps web php artisan migrate --no-interaction
Invoke-Docker compose run --rm --no-deps web php artisan db:seed --no-interaction
if (Test-Path -LiteralPath package-lock.json) {
    Invoke-Docker compose --profile tools run --rm node npm ci
} else {
    Invoke-Docker compose --profile tools run --rm node npm install
}
Invoke-Docker compose --profile tools run --rm node npm run build
# Evitar que Laravel use um servidor Vite antigo que nao esta em execucao.
if (Test-Path -LiteralPath public/hot) { Remove-Item -LiteralPath public/hot }
Invoke-Docker compose up -d --wait
if ($Frontend) { Invoke-Docker compose --profile frontend up -d vite }
Invoke-Docker compose ps
Write-Host ((Get-Content .env | Where-Object { $_ -match '^APP_URL=' }) -replace '^APP_URL=', 'Aplicacao: ')
Write-Host 'Versione composer.lock e package-lock.json apos revisar as dependencias.'
