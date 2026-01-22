@echo off
REM DigiMarker Chat API Test Script (Windows)
REM Usage: test_api.bat https://yourdomain.com

setlocal

set DOMAIN=%~1
if "%DOMAIN%"=="" set DOMAIN=http://localhost
set API_URL=%DOMAIN%/api/chat.php
set DIAGNOSTIC_URL=%DOMAIN%/api/diagnostic.php

echo ==========================================
echo DigiMarker Chat API Test
echo ==========================================
echo Domain: %DOMAIN%
echo.

REM Test 1: Diagnostic Endpoint
echo Test 1: Diagnostic Endpoint
echo URL: %DIAGNOSTIC_URL%
echo ----------------------------------------
curl -s "%DIAGNOSTIC_URL%"
echo.
echo.

REM Test 2: API Request
echo Test 2: API Request (POST)
echo URL: %API_URL%
echo Payload: {"question":"What is DigiMarker?"}
echo ----------------------------------------
curl -s -X POST "%API_URL%" ^
  -H "Content-Type: application/json" ^
  -H "Origin: %DOMAIN%" ^
  -d "{\"question\":\"What is DigiMarker?\"}"
echo.
echo.

echo ==========================================
echo Test Complete
echo ==========================================

endlocal

