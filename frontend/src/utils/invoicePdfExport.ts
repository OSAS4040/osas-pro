import type { jsPDF } from 'jspdf'

/**
 * Renders one full-page invoice image into a single A4 PDF page (scaled to fit).
 * Avoids the buggy multi-page loop that produced a blank second page.
 */
export function addInvoiceCanvasToSinglePagePdf(
  pdf: jsPDF,
  imgData: string,
  canvas: HTMLCanvasElement,
): void {
  const pageW = pdf.internal.pageSize.getWidth()
  const pageH = pdf.internal.pageSize.getHeight()
  const imgW = pageW
  const imgH = (canvas.height * imgW) / canvas.width
  if (imgH <= pageH) {
    pdf.addImage(imgData, 'PNG', 0, 0, imgW, imgH)
    return
  }
  const fitH = pageH
  const fitW = (canvas.width * fitH) / canvas.height
  const x = (pageW - fitW) / 2
  pdf.addImage(imgData, 'PNG', x, 0, fitW, fitH)
}
