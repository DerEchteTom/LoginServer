<#
    InstallMonitor_GUI.ps1 – korrigierte Version mit funktionierender Anzeige
    Stand: 2025-06-29
#>

# Modul bereitstellen
try {
    if (-not (Get-Module -ListAvailable -Name PSSQLite)) {
        Install-Module -Name PSSQLite -Scope CurrentUser -Force -ErrorAction Stop
    }
    Import-Module PSSQLite -ErrorAction Stop
}
catch {
    [System.Windows.Forms.MessageBox]::Show("Fehler beim Laden von PSSQLite:`r`n$($_.Exception.Message)","Fehler")
    exit 1
}

Add-Type -AssemblyName System.Windows.Forms

# feste SMTP-Konfiguration
$SmtpServer = '172.16.30.21'
$SmtpPort   = 25
$MailFrom   = 'monitor@technoteam.de'
$MailTo     = 'it@technoteam.de'

# Pfad zur Datenbank
$dbFile = Join-Path $PSScriptRoot 'data\installations.db'

# Fenster erstellen
$form = New-Object Windows.Forms.Form
$form.Text = 'Installation Monitor'
$form.Size = '800,600'
$form.StartPosition = 'CenterScreen'

# TableLayout als stabiles Raster (1 Spalte, 2 Zeilen)
$table = New-Object Windows.Forms.TableLayoutPanel
$table.Dock = 'Fill'
$table.RowCount = 2
$table.ColumnCount = 1
$table.RowStyles.Add((New-Object Windows.Forms.RowStyle('Absolute', 50)))
$table.RowStyles.Add((New-Object Windows.Forms.RowStyle('Percent', 100)))
$table.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('Percent', 100)))

# Panel in Zeile 0
$panel = New-Object Windows.Forms.Panel
$panel.Dock = 'Fill'
$panel.BackColor = 'WhiteSmoke'

# Bedienleiste mit RadioButtons und Buttons
$rbAll = New-Object Windows.Forms.RadioButton
$rbAll.Text = 'All installations'
$rbAll.AutoSize = $true
$rbAll.Left = 10
$rbAll.Top = 15
$rbAll.Checked = $true

$rbNew = New-Object Windows.Forms.RadioButton
$rbNew.Text = 'New since last run'
$rbNew.AutoSize = $true
$rbNew.Left = 150
$rbNew.Top  = 15

$btnRef = New-Object Windows.Forms.Button
$btnRef.Text = 'Refresh'
$btnRef.Width = 90
$btnRef.Left = 400
$btnRef.Top  = 12

$btnMail = New-Object Windows.Forms.Button
$btnMail.Text = 'Email New'
$btnMail.Width = 90
$btnMail.Left = 500
$btnMail.Top  = 12

$panel.Controls.AddRange(@($rbAll,$rbNew,$btnRef,$btnMail))

# DataGridView in Zeile 1
$grid = New-Object Windows.Forms.DataGridView
$grid.ReadOnly = $true
$grid.Dock = 'Fill'
$grid.AutoSizeColumnsMode = 'AllCells'
$grid.AutoSizeRowsMode = 'AllCells'
$grid.AllowUserToAddRows = $false
$grid.ColumnHeadersHeightSizeMode = 'AutoSize'
$grid.ColumnHeadersVisible = $true
$grid.RowHeadersVisible = $false

# Komponenten in das Raster einfügen
$table.Controls.Add($panel, 0, 0)
$table.Controls.Add($grid, 0, 1)

# Alles dem Formular hinzufügen
$form.Controls.Add($table)

# Zeilen nach dem Laden feinjustieren
$form.Add_Load({
    $grid.AutoResizeRows()
    $grid.ClearSelection()
})

# LastRun holen
$lastRec = Invoke-SqliteQuery -DataSource $dbFile -Query "SELECT Value FROM Metadata WHERE Key='LastRun';"
$lastRun = if ($lastRec) { [datetime]$lastRec.Value } else { Get-Date '1970-01-01' }

# Funktion zum Laden (überarbeitet)
function LoadData {
    if ($rbNew.Checked) {
        $param = @{ lr = $lastRun.ToString('yyyy-MM-dd HH:mm:ss') }
        $raw = Invoke-SqliteQuery -DataSource $dbFile `
               -Query "SELECT * FROM Installations WHERE TimeLogged > @lr ORDER BY TimeLogged DESC" `
               -SqlParameters $param
    }
    else {
        $raw = Invoke-SqliteQuery -DataSource $dbFile `
               -Query "SELECT * FROM Installations ORDER BY TimeLogged DESC"
    }

    # DataTable als Quelle, damit das Grid sauber bindet
    $table = New-Object System.Data.DataTable
    if ($raw.Count -gt 0) {
        $raw[0].PSObject.Properties.Name | ForEach-Object { [void]$table.Columns.Add($_) }
        foreach ($row in $raw) {
            $vals = $row.PSObject.Properties.Value
            [void]$table.Rows.Add($vals)
        }
    }

    $grid.DataSource = $null
    $grid.DataSource = $table

    [System.Windows.Forms.MessageBox]::Show("Geladen: $($table.Rows.Count) Einträge","Debug")
}

# Button-Ereignisse
$btnRef.Add_Click({ LoadData })

$btnMail.Add_Click({
    $param = @{ lr = $lastRun.ToString('yyyy-MM-dd HH:mm:ss') }
    $new = Invoke-SqliteQuery -DataSource $dbFile `
      -Query "SELECT * FROM Installations WHERE TimeLogged > @lr ORDER BY TimeLogged DESC" `
      -SqlParameters $param

    if ($new.Count -eq 0) {
        [System.Windows.Forms.MessageBox]::Show("Keine neuen Installationen seit $lastRun","Info")
        return
    }

    $body = "New installations since $lastRun`r`n"
    $new | ForEach-Object {
        $body += "$($_.DisplayName) (Installed: $($_.InstallDate))`r`n"
    }

    [System.Windows.Forms.MessageBox]::Show(
        "SMTP: ${SmtpServer}:${SmtpPort}`r`nFrom: ${MailFrom}`r`nTo: ${MailTo}`r`n`r`nBody:`r`n$body",
        "Mail Preview"
    )

    try {
        Send-MailMessage `
          -SmtpServer $SmtpServer -Port $SmtpPort `
          -From $MailFrom -To $MailTo `
          -Subject "InstallMonitor – new apps since $lastRun" `
          -Body $body
        [System.Windows.Forms.MessageBox]::Show("E-Mail wurde gesendet.","Info")
    }
    catch {
        [System.Windows.Forms.MessageBox]::Show("Fehler beim Senden der E-Mail:`r`n$($_.Exception.Message)","Fehler")
    }
})

# Initiales Laden + Anzeigen
LoadData
$form.ShowDialog()