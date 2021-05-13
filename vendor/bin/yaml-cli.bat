@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../grasmash/yaml-cli/bin/yaml-cli
php "%BIN_TARGET%" %*
