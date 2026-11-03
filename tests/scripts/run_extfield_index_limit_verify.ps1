# ExtField index-limit fix multi-PHP verification runner
$ErrorActionPreference = 'Continue'
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Probe = Join-Path $Root 'tests\scripts\extfield_index_limit_probe.php'

$Phps = @(
  @{ Name = 'php7.0.33'; Bin = 'D:\phpStudy\Extensions\php\php7.0.33nts\php.exe'; Ext = 'D:\phpStudy\Extensions\php\php7.0.33nts\ext' },
  @{ Name = 'php7.4.33'; Bin = 'D:\phpStudy\Extensions\php\php7.4.33nts\php.exe'; Ext = 'D:\phpStudy\Extensions\php\php7.4.33nts\ext' },
  @{ Name = 'php8.2.32'; Bin = 'D:\phpStudy\Extensions\php\php8.2.32nts\php.exe'; Ext = 'D:\phpStudy\Extensions\php\php8.2.32nts\ext' },
  @{ Name = 'php8.5.8';  Bin = 'D:\phpStudy\Extensions\php\php8.5.8nts\php.exe';  Ext = 'D:\phpStudy\Extensions\php\php8.5.8nts\ext' }
)

function Get-PhpExtArgs {
  param([hashtable]$Php)
  $loaded = @(& $Php.Bin -m 2>$null)
  $want = @('mysqli', 'pdo_mysql', 'pdo_sqlite', 'sqlite3')
  # php7.0 本机包缺少 pdo_mysql 依赖 DLL，强制加载只会告警且仍不可用
  if ($Php.Name -like 'php7.0*') {
    $want = @('mysqli', 'pdo_sqlite', 'sqlite3')
  }
  $need = New-Object System.Collections.Generic.List[string]
  foreach ($extName in $want) {
    if ($loaded -notcontains $extName) {
      $dll = Join-Path $Php.Ext ("php_{0}.dll" -f $extName)
      if (Test-Path $dll) {
        $need.Add('-d') | Out-Null
        $need.Add(("extension={0}" -f $extName)) | Out-Null
      }
    }
  }
  if ($need.Count -gt 0) {
    $all = New-Object System.Collections.Generic.List[string]
    $all.Add('-d') | Out-Null
    $all.Add(("extension_dir={0}" -f $Php.Ext)) | Out-Null
    foreach ($x in $need) { $all.Add($x) | Out-Null }
    return ,$all.ToArray()
  }
  return ,@()
}

function Invoke-Probe {
  param(
    [hashtable]$Php,
    [string[]]$ScenarioArgs
  )
  $extArgs = Get-PhpExtArgs -Php $Php
  $cmdArgs = @()
  if ($extArgs -and $extArgs.Count -gt 0) { $cmdArgs += $extArgs }
  $cmdArgs += @($Probe)
  if ($ScenarioArgs -and $ScenarioArgs.Count -gt 0) { $cmdArgs += $ScenarioArgs }
  $label = ($ScenarioArgs -join ' ')
  Write-Host ("---- {0} :: {1} ----" -f $Php.Name, $label) -ForegroundColor Cyan
  $lines = New-Object System.Collections.Generic.List[string]
  # 必须消费 stdout，否则会混入函数返回值导致 exit code 变成 Object[]
  & $Php.Bin @cmdArgs 2>&1 | ForEach-Object {
    $text = "$_"
    Write-Host $text
    $lines.Add($text) | Out-Null
  }
  $code = $LASTEXITCODE
  if ($null -eq $code) { $code = 1 }

  $result = $null
  foreach ($line in $lines) {
    if ($line -match '^RESULT=(.+)$') {
      $result = $Matches[1].Trim()
    }
  }
  if (-not $result) {
    if ($code -eq 0) { $result = 'PASS' }
    elseif ($code -eq 3) { $result = 'SKIP' }
    else { $result = 'FAIL' }
  }
  return [pscustomobject]@{
    Code = [int]$code
    Result = $result
  }
}

$results = New-Object System.Collections.Generic.List[object]
foreach ($php in $Phps) {
  if (-not (Test-Path $php.Bin)) {
    $results.Add([pscustomobject]@{ PHP = $php.Name; Scenario = 'ALL'; Status = 'MISSING_BIN'; Code = -1 }) | Out-Null
    continue
  }

  $scenarios = @(
    ,@('syntax_check')
    ,@('mysql_preserve_idx')
    ,@('mysql_orphan_retry')
    ,@('mysql_matrix')
    ,@('sqlite_100', 'sqlite')
    ,@('sqlite_100', 'pdo_sqlite')
  )

  foreach ($sc in $scenarios) {
    $probe = Invoke-Probe -Php $php -ScenarioArgs $sc
    $status = $probe.Result
    $results.Add([pscustomobject]@{
      PHP = $php.Name
      Scenario = ($sc -join ' ')
      Status = $status
      Code = $probe.Code
    }) | Out-Null
    if ($status -eq 'FAIL') {
      Write-Host ("FAILED {0} {1} exit={2}" -f $php.Name, ($sc -join ' '), $probe.Code) -ForegroundColor Red
    } elseif ($status -eq 'SKIP') {
      Write-Host ("SKIPPED {0} {1} exit={2}" -f $php.Name, ($sc -join ' '), $probe.Code) -ForegroundColor Yellow
    }
  }
}

Write-Host "`n======== SUMMARY ========" -ForegroundColor Yellow
$results | Format-Table -AutoSize
$passCount = @($results | Where-Object { $_.Status -eq 'PASS' }).Count
$skipCount = @($results | Where-Object { $_.Status -eq 'SKIP' }).Count
$failCount = @($results | Where-Object { $_.Status -ne 'PASS' -and $_.Status -ne 'SKIP' }).Count
Write-Host ("PASS_COUNT={0} SKIP_COUNT={1} FAIL_COUNT={2}" -f $passCount, $skipCount, $failCount)
# 必测场景：SKIP 表示未覆盖，不能验收通过
$blocked = @($results | Where-Object { $_.Status -ne 'PASS' })
if ($blocked.Count -gt 0) {
  Write-Host ("BLOCKED_COUNT={0} (FAIL or SKIP or MISSING_BIN)" -f $blocked.Count) -ForegroundColor Red
  exit 1
}
Write-Host 'BLOCKED_COUNT=0'
exit 0
