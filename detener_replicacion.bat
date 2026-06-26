REM Ocultar la ejecucion de comandos en pantalla
@echo off
echo Deteniendo replicador automatico...
echo.

REM Matar todos los procesos PHP que ejecutan cron_replicar_loop
taskkill /F /IM php.exe /FI "WINDOWTITLE eq Replicacion Naviera*"

echo Procesos PHP detenidos
echo.
pause