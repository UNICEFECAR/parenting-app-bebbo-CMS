@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../acquia/blt/bin/blt
php "%BIN_TARGET%" %*
