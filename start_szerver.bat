@echo off
title N/A - PHP Szerver
cls
echo ========================================================
echo   N/A - PHP Weboldal Inditasa
echo ========================================================
echo.

set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" (
    set "PHP_EXE=php"
)

echo A weboldal cime: http://localhost:8000
echo Rejtett kapu:    http://localhost:8000/na-zenekar-kapu.php
echo.
echo Leallitashoz nyomj Ctrl+C billentyut ebben az ablakban!
echo ========================================================
echo.

start http://localhost:8000
"%PHP_EXE%" -S localhost:8000

pause
