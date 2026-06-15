#Requires -Version 5.1
# Nexo FP - arranque con datos de demostración (Windows PowerShell)
# Carga los datos de demostración (fixtures) y arranca la aplicación.
# Uso: .\demo.ps1 [-Port 8080]
$env:LOAD_FIXTURES = "true"
& (Join-Path $PSScriptRoot "start.ps1") @args
