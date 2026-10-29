[CmdletBinding()]
param([string]$Filter)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$dockerCommand = Get-Command docker -ErrorAction SilentlyContinue
if ($dockerCommand) { $dockerExe = $dockerCommand.Source }
else { $dockerExe = Join-Path $env:ProgramFiles 'Docker\Docker\resources\bin\docker.exe' }
if (-not (Test-Path -LiteralPath vendor/autoload.php)) { throw 'Execute o setup do projeto antes dos testes.' }
& $dockerExe info --format '{{.OSType}}'
if ($LASTEXITCODE -ne 0) { throw 'Docker inacessivel.' }
$testExitCode = 1
try {
    $testArguments = @('compose', '-p', 'watchstream-tests', '-f', 'compose.tests.yaml', 'run', '--rm', '--build', 'tests', 'php', 'vendor/bin/phpunit', '--configuration=phpunit.mysql.xml')
    if ($Filter) { $testArguments += ('--filter=' + $Filter) }
    & $dockerExe @testArguments
    $testExitCode = $LASTEXITCODE
} finally {
    # Remove apenas os servicos efemeros de teste, sem tocar no projeto watchstream.
    & $dockerExe compose -p watchstream-tests -f compose.tests.yaml down --remove-orphans
    if ($LASTEXITCODE -ne 0 -and $testExitCode -eq 0) { $testExitCode = $LASTEXITCODE }
}
if ($testExitCode -ne 0) { throw ('Suite de testes terminou com codigo ' + $testExitCode + '. Veja os erros PHPUnit acima.') }
