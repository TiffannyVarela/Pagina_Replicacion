REM Ocultar la ejecucion de comandos en pantalla
@echo off
title Replicacion Naviera - Background Process
echo Iniciando replicador automatico...
echo.

REM Ejecutar PHP en segundo plano sin ventana
start /B php.exe -f "%~dp0cron_replicar.php"

echo Replicador iniciado en segundo plano
echo.
pause