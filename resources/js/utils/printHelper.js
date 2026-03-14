import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';


export function printBlock() { // Das "=" entfernt, das war ein Syntaxfehler
    const block = document.getElementById("printArea");
    if (!block) return; // Sicherheitscheck

    const newWindow = window.open('', '', 'width=900,height=700');

    // Styles der Hauptseite extrahieren
    const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'))
        .map(style => style.outerHTML)
        .join('');

    // Den gesamten HTML-Inhalt zusammenbauen
    const htmlContent = `
        <html>
        <head>
            <title>Ausgabeschein</title>
            ${styles}
            <style>
                /* Diese Regeln erzwingen das Aussehen für den Druck */
                @media print {
                    body { padding: 0; margin: 0; }

                    /* Entfernt den grauen Außenrahmen, Schatten und Abrundungen */
                    #printArea {
                        box-shadow: none !important;
                        border: none !important;
                        border-radius: 0 !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        width: 100% !important;
                    }

                    /* Stellt sicher, dass die Tabelle sauber bleibt */
                    table { border-collapse: collapse !important; width: 100%; }

                    /* Farben und Hintergründe erzwingen */
                    * {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="p-4">
                ${block.outerHTML}
            </div>
            <script>
                window.onload = () => {
                    setTimeout(() => {
                        window.print();
                        window.close();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `;

    newWindow.document.write(htmlContent);
    newWindow.document.close();
}

export function pdfBlock() {
    // Das Element finden (id="printArea" oder die .card)
    const element = document.getElementById("printArea") || document.querySelector('.card');
    if (!element) return;

    // --- OPTIK-FIX ---
    // Wir speichern die alten Styles, um sie nach dem "Foto" zurückzusetzen
    const oldShadow = element.style.boxShadow;
    const oldBorder = element.style.borderRadius;

    // Schatten und Rundungen für das PDF entfernen
    element.style.boxShadow = "none";
    element.style.borderRadius = "0";
    element.style.padding = "100px";
    element.style.width = "100%";

    html2canvas(element, {
        scale: 2, // Hohe Qualität
        useCORS: true
    }).then(canvas => {
        // Styles sofort wiederherstellen
        element.style.boxShadow = oldShadow;
        element.style.borderRadius = oldBorder;

        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');

        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

        pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);

        // --- NEUES FENSTER LOGIK ---
        // Wir erzeugen einen Blob (Daten-Objekt) vom PDF
        const blob = pdf.output('blob');
        const url = URL.createObjectURL(blob);

        // Neues Fenster mit der Blob-URL öffnen
        window.open(url, '_blank');
    });
}
