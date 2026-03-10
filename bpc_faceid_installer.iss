; Script generated for BPC Face ID System
[Setup]
AppName=BPC Face ID System
AppVersion=1.0
Publisher=EndDev
DefaultDirName=C:\inetpub\wwwroot\BPC_FaceID
DefaultGroupName=BPC Face ID System
OutputDir=.\Output
OutputBaseFilename=BPC_FaceID_Setup
Compression=lzma
SolidCompression=yes
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked

[Files]
; IMPORTANT: Place the Python installer in the same folder as this .iss file before compiling!
Source: "python-3.10.11-amd64.exe"; DestDir: "{tmp}"; Flags: ignoreversion deleteafterinstall

; Include the entire EndDev project EXCEPT .venv, .git, and .vscode (those will be generated/ignored)
Source: "*"; DestDir: "{app}"; Excludes: ".venv\*, .git\*, .vscode\*, Output\*, *.iss"; Flags: ignoreversion recursesubdirs createallsubdirs

[Run]
; 1. Silently install Python 3.10.11 for ALL USERS to C:\Program Files\Python310.
; This is critical to ensure IIS Service Accounts have access to Python.
Filename: "{tmp}\python-3.10.11-amd64.exe"; Parameters: "/quiet InstallAllUsers=1 PrependPath=1 Include_test=0"; StatusMsg: "Installing Python 3.10 globally... (This may take a minute)"; Flags: waituntilterminated

; 2. Run the environment setup batch. We are using standard pip instead of uv to completely avoid hardlink permission issues.
Filename: "{app}\setup_python_env.bat"; StatusMsg: "Setting up Python Virtual Environment & AI Models..."; Flags: waituntilterminated runhidden

; 3. Explicitly grant IIS Read/Execute permissions to the whole application directory
Filename: "{cmd}"; Parameters: "/c icacls ""{app}"" /grant ""IIS_IUSRS:(OI)(CI)RX"" /T /C /Q"; StatusMsg: "Setting up IIS web server permissions..."; Flags: runhidden waituntilterminated
Filename: "{cmd}"; Parameters: "/c icacls ""{app}"" /grant ""IUSR:(OI)(CI)RX"" /T /C /Q"; Flags: runhidden waituntilterminated

; 4. Explicitly grant Modify (Write) permissions so the PHP system can save Photos and Logs!
Filename: "{cmd}"; Parameters: "/c icacls ""{app}\uploads"" /grant ""IIS_IUSRS:(OI)(CI)M"" /T /C /Q"; Flags: runhidden waituntilterminated
Filename: "{cmd}"; Parameters: "/c icacls ""{app}\logs"" /grant ""IIS_IUSRS:(OI)(CI)M"" /T /C /Q"; Flags: runhidden waituntilterminated

[Icons]
Name: "{group}\Face ID Kiosk"; Filename: "{app}\.venv\Scripts\pythonw.exe"; Parameters: """{app}\faceid\start_kiosk.py"""
Name: "{autodesktop}\Face ID Kiosk"; Filename: "{app}\.venv\Scripts\pythonw.exe"; Parameters: """{app}\faceid\start_kiosk.py"""; Tasks: desktopicon
