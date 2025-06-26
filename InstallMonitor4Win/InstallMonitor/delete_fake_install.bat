@echo off
set "DB=C:\LocProc\InstallMonitor\data\installations.db"
set "APPNAME=TestApp123"

sqlite3 "%DB%" "DELETE FROM Installations WHERE DisplayName = '%APPNAME%';"
echo Fake installation '%APPNAME%' entfernt.
pause