@echo off
rem ============================================================
rem  Golf Management System - development launcher
rem
rem  Uses whatever PHP is on your PATH (XAMPP's PHP 8.2.12),
rem  then starts the Laravel server, the queue worker and the
rem  Vite asset server together.
rem
rem  Usage: double-click this file, or run  start-dev  here.
rem  Stop everything with Ctrl+C.
rem ============================================================
setlocal
cd /d "%~dp0"

echo.
echo === PHP being used ===
php -v
echo.
echo === App URL: http://127.0.0.1:8000 ===
echo.

composer run dev
