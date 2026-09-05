Set-Location "C:\Users\gfoumbi\Downloads\Afrik Appart\repository"

powershell -ExecutionPolicy Bypass -File .\scripts\build-release.ps1

powershell -ExecutionPolicy Bypass `
  -File .\scripts\deploy-bluehost.ps1 `
  -CredentialsPath "C:\Users\gfoumbi\Downloads\Afrik Appart\.bluehost-credentials.json"