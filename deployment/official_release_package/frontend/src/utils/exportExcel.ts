import ExcelJS from 'exceljs'

/** تصدير صفوف مسطّحة إلى ‎.xlsx (بديل آمن عن حزمة ‎xlsx غير المصلحة في npm audit). */
export async function downloadExcelFromRows(
  rows: Record<string, unknown>[],
  sheetName: string,
  fileName: string,
): Promise<void> {
  if (!rows.length) return

  const keys = Object.keys(rows[0])
  const safeSheet = (sheetName || 'Sheet').slice(0, 31)

  const wb = new ExcelJS.Workbook()
  const ws = wb.addWorksheet(safeSheet)

  ws.addRow(keys)
  const header = ws.getRow(1)
  header.font = { bold: true }

  for (const r of rows) {
    ws.addRow(
      keys.map((k) => {
        const v = r[k]
        if (v === null || v === undefined) return ''
        if (typeof v === 'object') return JSON.stringify(v)
        return v
      }),
    )
  }

  keys.forEach((_, i) => {
    ws.getColumn(i + 1).width = 18
  })

  const buf = await wb.xlsx.writeBuffer()
  const blob = new Blob([buf], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = fileName.endsWith('.xlsx') ? fileName : `${fileName}.xlsx`
  a.click()
  URL.revokeObjectURL(url)
}
