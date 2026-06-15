@echo off
:: Nexo FP - arranque con datos de demostracion (Windows)
:: Carga los datos de demostracion (fixtures) y arranca la aplicacion.
:: Uso: demo.bat [puerto]          (por defecto: 8080)
set LOAD_FIXTURES=true
call "%~dp0start.bat" %*
