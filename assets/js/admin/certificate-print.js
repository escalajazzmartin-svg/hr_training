document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-certificate-print]');

    if (!button || button.disabled) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    const employee = button.dataset.employee || 'N/A';
    const employeeId = button.dataset.employeeId || 'N/A';
    const program = button.dataset.program || 'N/A';
    const score = button.dataset.score || 'N/A';
    const certificateCode = button.dataset.certificateCode || 'N/A';
    const issuedAt = button.dataset.issuedAt || '';

    function escapeHTML(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            const entities = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };

            return entities[character];
        });
    }

    let formattedDate = 'Date unavailable';

    if (issuedAt) {
        const parsedDate = new Date(issuedAt.replace(' ', 'T'));

        if (!Number.isNaN(parsedDate.getTime())) {
            formattedDate = parsedDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    }

    // Remove leftover print elements.
    document.getElementById('certificate-print-container')?.remove();
    document.getElementById('certificate-print-style')?.remove();

    // Create the temporary certificate.
    const container = document.createElement('div');
    container.id = 'certificate-print-container';

    container.innerHTML = `
        <main class="certificate">
            <div class="certificate-content">

                <div class="organization">
                    WIS HR Training and Development
                </div>

                <h1>Certificate of Completion</h1>

                <p class="subtitle">
                    This certificate is proudly presented to
                </p>

                <div class="recipient">
                    ${escapeHTML(employee)}
                </div>

                <p class="description">
                    In recognition of successfully completing the training program
                </p>

                <div class="program">
                    ${escapeHTML(program)}
                </div>

                <p class="score">
                    with an assessment score of
                    <strong>${escapeHTML(score)}%</strong>.
                </p>

                <table class="details" cellspacing="0" cellpadding="0">
                    <tr>
                        <td>
                            <span class="detail-label">Employee ID</span>
                            <span class="detail-value">
                                ${escapeHTML(employeeId)}
                            </span>
                        </td>

                        <td>
                            <span class="detail-label">Date Issued</span>
                            <span class="detail-value">
                                ${escapeHTML(formattedDate)}
                            </span>
                        </td>
                    </tr>
                </table>

                <div class="certificate-code">
                    Certificate Code:
                    <strong>${escapeHTML(certificateCode)}</strong>
                </div>

            </div>
        </main>
    `;

    // Add certificate-specific print styles.
    const printStyle = document.createElement('style');
    printStyle.id = 'certificate-print-style';

    printStyle.textContent = `
        #certificate-print-container {
            display: none;
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }

        @media print {
            html,
            body {
                width: 297mm !important;
                height: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                background: #ffffff !important;
                font-family: Georgia, "Times New Roman", serif !important;
                color: #262626 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body * {
                visibility: hidden !important;
            }

            #certificate-print-container,
            #certificate-print-container * {
                visibility: visible !important;
            }

            #certificate-print-container {
                display: block !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 297mm !important;
                height: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                background: #ffffff !important;
                z-index: 2147483647 !important;
            }

            /* Certificate frame: fixed position and dimensions. */
            #certificate-print-container .certificate {
                position: absolute !important;
                left: 10mm !important;
                top: 10mm !important;
                width: 277mm !important;
                height: 190mm !important;
                margin: 0 !important;
                padding: 12mm !important;
                border: 1.2mm double #8B1E1E !important;
                box-sizing: border-box !important;
                text-align: center !important;
                overflow: hidden !important;
                background: #ffffff !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            /* Inner gold border. */
            #certificate-print-container .certificate::before {
                content: "" !important;
                position: absolute !important;
                left: 3mm !important;
                top: 3mm !important;
                right: 3mm !important;
                bottom: 3mm !important;
                border: 0.45mm solid #F2C300 !important;
                pointer-events: none !important;
            }

            /*
             * Shift the content downward by 30mm.
             * The certificate frame stays fixed.
             */
            #certificate-print-container .certificate-content {
                position: relative !important;
                top: 30mm !important;
                padding: 0 !important;
                margin: 0 !important;
                transform: none !important;
            }

            #certificate-print-container .organization {
                margin: 1mm 0 0 !important;
                color: #8B1E1E !important;
                font-family: Arial, sans-serif !important;
                font-size: 10pt !important;
                font-weight: bold !important;
                letter-spacing: 2.5pt !important;
                text-transform: uppercase !important;
            }

            #certificate-print-container h1 {
                margin: 7mm 0 4mm !important;
                color: #8B1E1E !important;
                font-family: Georgia, "Times New Roman", serif !important;
                font-size: 25pt !important;
                font-weight: bold !important;
                line-height: 1.15 !important;
                letter-spacing: 1.3pt !important;
                text-transform: uppercase !important;
            }

            #certificate-print-container .subtitle {
                margin: 0 !important;
                font-family: Georgia, "Times New Roman", serif !important;
                font-size: 12pt !important;
                font-weight: normal !important;
                line-height: normal !important;
            }

            #certificate-print-container .recipient {
                width: 82% !important;
                margin: 4mm auto !important;
                padding: 0 0 2.5mm !important;
                border: 0 !important;
                border-bottom: 0.3mm solid #999999 !important;
                font-family: Georgia, "Times New Roman", serif !important;
                font-size: 23pt !important;
                font-weight: bold !important;
                line-height: normal !important;
                overflow-wrap: anywhere !important;
                box-sizing: border-box !important;
            }

            #certificate-print-container .description {
                max-width: none !important;
                margin: 3.5mm auto 0 !important;
                font-family: Georgia, "Times New Roman", serif !important;
                font-size: 12pt !important;
                font-weight: normal !important;
                line-height: 1.35 !important;
            }

            #certificate-print-container .program {
                margin: 3mm 0 0 !important;
                color: #8B1E1E !important;
                font-family: Georgia, "Times New Roman", serif !important;
                font-size: 19pt !important;
                font-weight: bold !important;
                line-height: normal !important;
                overflow-wrap: anywhere !important;
            }

            #certificate-print-container .score {
                margin: 3mm 0 0 !important;
                font-family: Georgia, "Times New Roman", serif !important;
                font-size: 11pt !important;
                font-weight: normal !important;
                line-height: normal !important;
            }

            #certificate-print-container .details {
                display: table !important;
                width: 78% !important;
                margin: 8mm auto 0 !important;
                border-collapse: collapse !important;
                table-layout: fixed !important;
                font-family: Arial, sans-serif !important;
                font-size: 9pt !important;
            }

            #certificate-print-container .details tr {
                display: table-row !important;
            }

            #certificate-print-container .details,
            #certificate-print-container .details tr,
            #certificate-print-container .details td,
            #certificate-print-container .detail-label,
            #certificate-print-container .detail-value {
                border: none !important;
                border-bottom: none !important;
                box-shadow: none !important;
            }

            #certificate-print-container .details td {
                display: table-cell !important;
                width: 50% !important;
                padding: 0 8mm !important;
                text-align: center !important;
                vertical-align: top !important;
                overflow-wrap: anywhere !important;
            }

            #certificate-print-container .detail-label {
                display: block !important;
                margin-bottom: 1.5mm !important;
                color: #666666 !important;
                font-family: Arial, sans-serif !important;
                font-size: 8pt !important;
                font-weight: normal !important;
                letter-spacing: 1pt !important;
                text-transform: uppercase !important;
            }

            #certificate-print-container .detail-value {
                display: block !important;
                font-family: Arial, sans-serif !important;
                font-size: 9pt !important;
                font-weight: bold !important;
                line-height: normal !important;
                overflow-wrap: anywhere !important;
            }

            #certificate-print-container .certificate-code {
                margin-top: 6mm !important;
                font-family: Arial, sans-serif !important;
                font-size: 9pt !important;
                font-weight: normal !important;
                line-height: normal !important;
                overflow-wrap: anywhere !important;
            }

            #certificate-print-container .certificate-code strong {
                font-weight: bold !important;
            }
        }
        #certificate-print-container .details td::before,
        #certificate-print-container .details td::after,
        #certificate-print-container .detail-label::before,
        #certificate-print-container .detail-label::after,
        #certificate-print-container .detail-value::before,
        #certificate-print-container .detail-value::after {
            content: none !important;
            display: none !important;
        }
    `;

    document.head.appendChild(printStyle);
    document.body.appendChild(container);

    // Open Chrome's native Print dialog in the current tab.
    // Wait briefly to ensure the print styles are applied.
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            window.print();
        });
    });

    // Clean up after printing or cancelling.
    function cleanup() {
        container.remove();
        printStyle.remove();
        window.removeEventListener('afterprint', cleanup);
    }

    window.addEventListener('afterprint', cleanup, { once: true });
}, true);