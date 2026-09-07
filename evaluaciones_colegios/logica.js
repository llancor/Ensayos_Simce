        /* ── HELPERS ── */
        let questions = [];
        let generatedHTML = '';
        let testGeneratedAtLeastOnce = false;

        /* ── SCORING MODE ── */
        function toggleScoringMode() {
            const mode = document.getElementById('cfg-modo-puntaje').value;
            const fieldMax = document.getElementById('field-puntaje-max');
            if (mode === 'manual') {
                fieldMax.style.display = 'none';
            } else {
                fieldMax.style.display = '';
            }
            hideGeneratedStep();
        }

        function getScoringMode() {
            return document.getElementById('cfg-modo-puntaje').value;
        }

        function getQuestionPoints(idx) {
            const mode = getScoringMode();
            if (mode === 'manual') {
                return parseFloat(questions[idx]['Puntaje']) || 1;
            }
            return null; // proporcional
        }

        function calcPuntajeMaxManual() {
            let sum = 0;
            questions.forEach((q, i) => { sum += (parseFloat(q['Puntaje']) || 1); });
            return sum;
        }

        /* ── FORM BUILDER ── */
        let fbQuestions = [];

        function switchMode(mode) {
            document.getElementById('mode-excel').style.display = mode === 'excel' ? '' : 'none';
            document.getElementById('mode-form').style.display = mode === 'form' ? '' : 'none';
            document.getElementById('tab-excel').classList.toggle('active', mode === 'excel');
            document.getElementById('tab-form').classList.toggle('active', mode === 'form');
            if (mode === 'form') {
                if (fbQuestions.length === 0) {
                    let hasValidDraft = false;
                    try {
                        const draft = localStorage.getItem('pruebas_draft');
                        if (draft && JSON.parse(draft).length > 0) {
                            hasValidDraft = true;
                        }
                    } catch (e) {
                        console.warn('Draft check failed', e);
                    }
                    
                    if (!hasValidDraft) {
                        addFormQuestion(); // Si no hay borrador, agregar una pregunta vacía
                    } else {
                        renderFormBuilder(); // Si hay borrador, mostramos la UI vacía esperando que el usuario elija en la alerta
                    }
                } else {
                    renderFormBuilder(); // Si ya hay preguntas, solo las volvemos a mostrar
                }
            }
        }

        function escHtml(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function safeImageSrc(src) {
            var value = String(src || '');
            return /^(data:image\/(?:png|jpeg|jpg|gif|webp);base64,|https?:\/\/)/i.test(value) ? value : '';
        }

        function safeServerTestUrl(src) {
            try {
                var parsed = new URL(String(src || ''), window.location.href);
                var directHtml = parsed.pathname.indexOf('/pruebas/') !== -1 && /\.html?$/i.test(parsed.pathname);
                var protectedFile = parsed.pathname.endsWith('/archivo_prueba.php') && /^\d+$/.test(parsed.searchParams.get('id') || '');
                return parsed.origin === window.location.origin && (directHtml || protectedFile)
                    ? parsed.href
                    : '';
            } catch (error) {
                return '';
            }
        }

        function jsonForScript(value) {
            return JSON.stringify(value).replace(/[<>&\u2028\u2029]/g, function(character) {
                return {
                    '<': '\\u003C',
                    '>': '\\u003E',
                    '&': '\\u0026',
                    '\u2028': '\\u2028',
                    '\u2029': '\\u2029'
                }[character];
            });
        }

        function syncFbQuestion(idx) {
            var card = document.getElementById('fbq-' + idx);
            if (!card) return;
            fbQuestions[idx].text    = card.querySelector('.fb-q-text').value;
            fbQuestions[idx].a       = card.querySelector('.fb-alt-a').value;
            fbQuestions[idx].b       = card.querySelector('.fb-alt-b').value;
            fbQuestions[idx].c       = card.querySelector('.fb-alt-c').value;
            fbQuestions[idx].d       = card.querySelector('.fb-alt-d').value;
            fbQuestions[idx].skill   = card.querySelector('.fb-skill-input').value;
            var passEl = card.querySelector('.fb-passage-input');
            if (passEl) fbQuestions[idx].passage = passEl.value;
            var ptsEl = card.querySelector('.fb-pts-input');
            if (ptsEl) fbQuestions[idx].points = parseFloat(ptsEl.value) || 1;
            var checked = card.querySelector('input[name="fb-correct-' + idx + '"]:checked');
            fbQuestions[idx].correct = checked ? checked.value : '';
        }

        function syncAllFbQuestions() {
            fbQuestions.forEach(function(_, i) { syncFbQuestion(i); });
        }

        /* ── DRAFT (AUTOSAVE) ── */
        let draftTimeout = null;
        function saveDraft() {
            if (fbQuestions.length === 0) {
                localStorage.removeItem('pruebas_draft');
                return;
            }
            const hasContent = fbQuestions.some(q => (q.text || '').trim() !== '' || (q.a || '').trim() !== '' || (q.b || '').trim() !== '' || (q.c || '').trim() !== '' || (q.d || '').trim() !== '' || q.img || (q.passage || '').trim() !== '');
            if (!hasContent) {
                localStorage.removeItem('pruebas_draft');
                return;
            }
            localStorage.setItem('pruebas_draft', JSON.stringify(fbQuestions));
        }

        function debouncedSaveDraft() {
            clearTimeout(draftTimeout);
            draftTimeout = setTimeout(() => {
                syncAllFbQuestions();
                saveDraft();
            }, 1000);
        }

        function loadDraft() {
            const draft = localStorage.getItem('pruebas_draft');
            if (draft) {
                try {
                    const parsed = JSON.parse(draft);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        fbQuestions = parsed;
                        renderFormBuilder();
                        clearDraftUI();
                        toast('✅ Borrador recuperado con ' + fbQuestions.length + ' preguntas.');
                    }
                } catch (e) {
                    console.error('Error parsing draft', e);
                }
            }
        }

        function clearDraftUI() {
            document.getElementById('draft-alert').style.display = 'none';
        }

        function dismissDraft() {
            clearDraftUI();
            clearDraft();
            if (fbQuestions.length === 0 && document.getElementById('mode-form').style.display !== 'none') {
                addFormQuestion();
            }
        }

        function clearDraft() {
            localStorage.removeItem('pruebas_draft');
        }

        function renderFormBuilder() {
            var container = document.getElementById('fb-container');
            var contBtn = document.getElementById('btn-fb-continue');
            container.innerHTML = '';

            if (fbQuestions.length === 0) {
                container.innerHTML = '<div class="fb-empty-state"><div style="font-size:2rem;">📝</div><p style="margin-top:0.5rem;">Haz clic en "+ Agregar Pregunta" para comenzar.</p></div>';
                contBtn.disabled = true;
                contBtn.textContent = 'Continuar →';
                return;
            }

            fbQuestions.forEach(function(q, i) {
                var imgSection = q.img
                    ? '<div class="fb-img-zone"><img class="fb-img-preview" src="' + q.img + '" title="Imagen pregunta ' + (i + 1) + '" onclick="document.getElementById(\'fb-img-input-' + i + '\').click()"><button class="img-remove-btn" onclick="removeFbImage(' + i + ')">✕ Quitar</button></div>'
                    : '<div style="display:flex; gap:0.5rem; flex-wrap:wrap;"><label class="img-upload-btn" for="fb-img-input-' + i + '">📷 Subir imagen desde el PC</label><button type="button" class="img-search-btn" onclick="openImageSearch(' + i + ')">🖼️ Buscar imagen online</button></div>';

                var delBtn = fbQuestions.length > 1
                    ? '<button class="fb-q-del" onclick="syncAllFbQuestions();removeFormQuestion(' + i + ')">🗑 Eliminar</button>'
                    : '';

                var altsHtml = ['A', 'B', 'C', 'D'].map(function(letter) {
                    var lc = letter.toLowerCase();
                    var altInputId = 'fb-alt-input-' + i + '-' + lc;
                    var altPreviewId = 'fb-alt-preview-' + i + '-' + lc;
                    var mathAltBtn = mathModeEnabled
                        ? '<button class="fb-math-btn-alt" onclick="openFormulaModalForInput(\'' + altInputId + '\')" title="Insertar fórmula en alternativa ' + letter + '">∑</button>'
                        : '';
                    var altPreview = mathModeEnabled
                        ? '<div class="fb-alt-math-preview" id="' + altPreviewId + '"></div>'
                        : '';
                    return '<div class="fb-alt-row">'
                        + '<input type="radio" class="fb-alt-radio" name="fb-correct-' + i + '" value="' + letter + '"'
                        + (q.correct === letter ? ' checked' : '') + '>'
                        + '<div class="fb-alt-letter">' + letter + '</div>'
                        + '<div class="fb-alt-input-wrap">'
                        + '<div class="fb-alt-input-row">'
                        + '<input type="text" id="' + altInputId + '" class="fb-alt-input fb-alt-' + lc + '" placeholder="Alternativa ' + letter + '..." value="' + escHtml(q[lc]) + '">'
                        + mathAltBtn
                        + '</div>'
                        + altPreview
                        + '</div>'
                        + '</div>';
                }).join('');

                var mathBtn = mathModeEnabled
                    ? '<button class="fb-math-btn" onclick="openFormulaModal(\'fbq-' + i + '\')">∑ Fórmula</button>'
                    : '';
                var mathPreview = mathModeEnabled
                    ? '<div class="fb-math-preview" id="fb-math-preview-' + i + '"><span style="color:var(--text-muted);font-size:0.8rem;">Vista previa con fórmulas renderizadas</span></div>'
                    : '';

                var passageDisplay = q.passage ? 'style="display:block;"' : 'style="display:none;"';
                var copyPrevBtn = (i > 0 && fbQuestions[i-1].passage) ? '<button class="btn-link-small" onclick="copyPassageFromPrevious(' + i + ')">Copiar texto de la pregunta anterior</button>' : '';
                var clearPassageBtn = q.passage ? '<button class="btn-link-small" style="color:var(--danger);" onclick="clearPassage(' + i + ')">🗑 Eliminar texto</button>' : '';
                var passageHtml = '<div style="margin-bottom:0.5rem;"><div class="fb-passage-toggle" onclick="togglePassage(' + i + ')">📖 Añadir texto de lectura</div>' + copyPrevBtn + clearPassageBtn + '</div>'
                    + '<textarea id="fb-passage-' + i + '" class="fb-passage-input" ' + passageDisplay + ' placeholder="Escribe o pega el texto de comprensión lectora aquí...">' + escHtml(q.passage) + '</textarea>';

                var ptsHtml = '';
                if (getScoringMode() === 'manual') {
                    ptsHtml = '<input type="number" class="fb-pts-input" placeholder="Pts" value="' + (q.points || 1) + '" min="0" max="999" step="0.5" style="width:70px;padding:0.45rem 0.5rem;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);font-size:0.85rem;font-weight:600;font-family:Inter,sans-serif;text-align:center;" title="Puntaje de esta pregunta">';
                }

                var card = document.createElement('div');
                card.className = 'fb-question-card';
                card.id = 'fbq-' + i;
                card.innerHTML = '<div class="fb-q-header"><span class="fb-q-num">Pregunta ' + (i + 1) + '</span>' + mathBtn + delBtn + '</div>'
                    + passageHtml
                    + '<textarea class="fb-q-text" placeholder="Escribe el enunciado de la pregunta...">' + escHtml(q.text) + '</textarea>'
                    + mathPreview
                    + '<p class="fb-correct-hint">✅ Marca el círculo de la alternativa correcta:</p>'
                    + '<div class="fb-alts">' + altsHtml + '</div>'
                    + '<div class="fb-q-footer">'
                    + '<input type="text" class="fb-skill-input" placeholder="Habilidad / eje temático (opcional)" value="' + escHtml(q.skill) + '">'
                    + ptsHtml
                    + imgSection
                    + '<input type="file" id="fb-img-input-' + i + '" accept="image/*" style="display:none" onchange="handleFbImage(this.files[0],' + i + ');this.value=\'\';">'
                    + '</div>';
                container.appendChild(card);
            });

            contBtn.disabled = false;
            var n = fbQuestions.length;
            contBtn.textContent = 'Continuar con ' + n + ' pregunta' + (n !== 1 ? 's' : '') + ' →';
        }

        function addFormQuestion() {
            syncAllFbQuestions();
            fbQuestions.push({ text: '', a: '', b: '', c: '', d: '', correct: '', skill: '', img: '', passage: '', points: 1 });
            renderFormBuilder();
            saveDraft();
            // Scroll to the new card
            var container = document.getElementById('fb-container');
            if (container.lastElementChild) {
                container.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function removeFormQuestion(idx) {
            fbQuestions.splice(idx, 1);
            renderFormBuilder();
            saveDraft();
        }

        function handleFbImage(file, idx) {
            if (!file || !file.type.startsWith('image/')) {
                toast('⚠️ Solo se aceptan archivos de imagen (JPG, PNG, GIF, WebP)', 'error');
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                syncFbQuestion(idx);
                fbQuestions[idx].img = e.target.result;
                renderFormBuilder();
                toast('🖼️ Imagen cargada para la pregunta ' + (idx + 1));
            };
            reader.readAsDataURL(file);
        }

        function removeFbImage(idx) {
            syncFbQuestion(idx);
            fbQuestions[idx].img = '';
            renderFormBuilder();
        }

        function togglePassage(idx) {
            var ta = document.getElementById('fb-passage-' + idx);
            if (ta.style.display === 'none' || !ta.style.display) {
                ta.style.display = 'block'; ta.focus();
            } else {
                ta.style.display = 'none';
            }
        }

        function copyPassageFromPrevious(idx) {
            syncFbQuestion(idx - 1);
            if (fbQuestions[idx - 1].passage) {
                document.getElementById('fb-passage-' + idx).value = fbQuestions[idx - 1].passage;
                document.getElementById('fb-passage-' + idx).style.display = 'block';
                syncFbQuestion(idx);
                renderFormBuilder();
            } else { toast('La pregunta anterior no tiene texto.', 'error'); }
        }

        function clearPassage(idx) {
            var ta = document.getElementById('fb-passage-' + idx);
            if (ta) { ta.value = ''; ta.style.display = 'none'; }
            syncFbQuestion(idx);
            renderFormBuilder();
            toast('🗑️ Texto eliminado de la pregunta ' + (idx + 1));
        }

        /* ── MATH MODE ── */
        let mathModeEnabled = false;
        let focusedFbInput = null;

        var FORMULA_QUICK = [
            { label: 'a/b',    latex: '\\frac{a}{b}' },
            { label: 'x²',     latex: '^{2}' },
            { label: 'xⁿ',     latex: '^{n}' },
            { label: 'xₙ',     latex: '_{n}' },
            { label: '√x',     latex: '\\sqrt{x}' },
            { label: '∛x',     latex: '\\sqrt[3]{x}' },
            { label: '|x|',    latex: '|x|' },
            { label: '±',      latex: '\\pm' },
            { label: '×',      latex: '\\times' },
            { label: '÷',      latex: '\\div' },
            { label: '≤',      latex: '\\leq' },
            { label: '≥',      latex: '\\geq' },
            { label: '≠',      latex: '\\neq' },
            { label: '≈',      latex: '\\approx' },
            { label: '∞',      latex: '\\infty' },
            { label: 'π',      latex: '\\pi' },
            { label: 'α',      latex: '\\alpha' },
            { label: 'β',      latex: '\\beta' },
            { label: 'θ',      latex: '\\theta' },
            { label: 'Δ',      latex: '\\Delta' },
            { label: '°',      latex: '^{\\circ}' },
            { label: 'Σ',      latex: '\\sum_{i=1}^{n}' },
            { label: '∫',      latex: '\\int_{a}^{b}' },
            { label: 'lim',    latex: '\\lim_{x \\to \\infty}' },
            { label: 'sin',    latex: '\\sin' },
            { label: 'cos',    latex: '\\cos' },
            { label: 'tan',    latex: '\\tan' },
            { label: 'log',    latex: '\\log' },
            { label: 'ln',     latex: '\\ln' },
            { label: '(  )',   latex: '\\left( \\right)' },
        ];

        function setMathMode(enabled) {
            mathModeEnabled = enabled;
            var cb = document.getElementById('math-mode-checkbox');
            if (cb) cb.checked = enabled;
            if (fbQuestions.length > 0) renderFormBuilder();
        }

        function openFormulaModal(cardId) {
            var card = document.getElementById(cardId);
            var targetInput = null;
            if (card) {
                if (focusedFbInput && card.contains(focusedFbInput)) {
                    targetInput = focusedFbInput;
                } else {
                    targetInput = card.querySelector('.fb-q-text');
                }
            }
            document.getElementById('formula-modal')._targetInput = targetInput;
            resetFormulaModal();
            document.getElementById('formula-modal').style.display = 'flex';
        }

        function closeFormulaModal() {
            document.getElementById('formula-modal').style.display = 'none';
        }

        function updateFormulaPreview() {
            var input = document.getElementById('formula-latex-input').value.trim();
            var preview = document.getElementById('formula-preview-area');
            if (!input) {
                preview.innerHTML = '<span class="formula-display-placeholder">Haz clic en los botones para crear tu fórmula...</span>';
                return;
            }
            try {
                var displayMode = document.getElementById('formula-display-mode').checked;
                preview.innerHTML = katex.renderToString(input, { throwOnError: false, displayMode: displayMode });
            } catch(e) {
                preview.innerHTML = '<span style="color:var(--danger);font-size:0.82rem;">⚠ ' + escHtml(e.message) + '</span>';
            }
        }

        function appendQuickFormula(latex) {
            var input = document.getElementById('formula-latex-input');
            var start = input.selectionStart;
            var end = input.selectionEnd;
            input.value = input.value.substring(0, start) + latex + input.value.substring(end);
            input.selectionStart = input.selectionEnd = start + latex.length;
            input.focus();
            updateFormulaPreview();
        }

        function insertFormulaIntoInput() {
            var latex = document.getElementById('formula-latex-input').value.trim();
            if (!latex) { toast('⚠️ Escribe una fórmula primero', 'error'); return; }
            var displayMode = document.getElementById('formula-display-mode').checked;
            var wrapped = displayMode ? '$$' + latex + '$$' : '$' + latex + '$';
            var target = document.getElementById('formula-modal')._targetInput;
            if (!target) { toast('⚠️ Haz clic primero en el campo donde deseas insertar la fórmula', 'error'); return; }
            var start = target.selectionStart != null ? target.selectionStart : target.value.length;
            var end   = target.selectionEnd   != null ? target.selectionEnd   : target.value.length;
            target.value = target.value.substring(0, start) + wrapped + target.value.substring(end);
            target.focus();
            target.selectionStart = target.selectionEnd = start + wrapped.length;
            // Sync back to fbQuestions
            var cardEl = target.closest('.fb-question-card');
            if (cardEl) {
                var idx = parseInt(cardEl.id.replace('fbq-', ''));
                syncFbQuestion(idx);
                updateMathPreviewForCard(idx);
            }
            closeFormulaModal();
            toast('✅ Fórmula insertada');
        }

        function renderTextWithMath(text) {
            if (typeof katex === 'undefined') return escHtml(text);
            var result = '';
            var lastIndex = 0;
            // Match $$...$$ first (display), then $...$  (inline)
            var pattern = /(\$\$[\s\S]+?\$\$|\$[^\$\n]+?\$)/g;
            var match;
            while ((match = pattern.exec(text)) !== null) {
                result += escHtml(text.substring(lastIndex, match.index));
                var full = match[0];
                var isDisplay = full.startsWith('$$');
                var inner = isDisplay ? full.slice(2, -2) : full.slice(1, -1);
                try {
                    result += katex.renderToString(inner, { throwOnError: false, displayMode: isDisplay });
                } catch(e) {
                    result += escHtml(full);
                }
                lastIndex = match.index + full.length;
            }
            result += escHtml(text.substring(lastIndex));
            return result;
        }

        function updateMathPreviewForCard(idx) {
            var card = document.getElementById('fbq-' + idx);
            if (!card) return;

            // Vista previa del enunciado
            var previewEl = document.getElementById('fb-math-preview-' + idx);
            if (previewEl) {
                var text = card.querySelector('.fb-q-text').value;
                previewEl.innerHTML = text.trim()
                    ? renderTextWithMath(text)
                    : '<span style="color:var(--text-muted);font-size:0.8rem;">Vista previa con fórmulas renderizadas</span>';
            }

            // Vista previa de cada alternativa
            ['a', 'b', 'c', 'd'].forEach(function(lc) {
                var altInput   = document.getElementById('fb-alt-input-' + idx + '-' + lc);
                var altPreview = document.getElementById('fb-alt-preview-' + idx + '-' + lc);
                if (!altInput || !altPreview) return;
                var val = altInput.value;
                if (val && /\$/.test(val)) {
                    altPreview.innerHTML = renderTextWithMath(val);
                    altPreview.style.display = 'block';
                } else {
                    altPreview.innerHTML = '';
                    altPreview.style.display = 'none';
                }
            });
        }

        function openFormulaModalForInput(inputId) {
            var input = document.getElementById(inputId);
            if (input) {
                focusedFbInput = input;
                document.getElementById('formula-modal')._targetInput = input;
            }
            resetFormulaModal();
            document.getElementById('formula-modal').style.display = 'flex';
        }

        /* ── MATH KEYBOARD DATA ── */
        var MK_ROWS = [
            [
                {l:'x',   x:'x',           t:'var'},
                {l:'y',   x:'y',           t:'var'},
                {l:'xⁿ',  x:'^{}',         t:'fn', c:1},
                {l:'xₙ',  x:'_{}',         t:'fn', c:1},
                {l:'[ ]', x:'[]',          t:'op'},
                {l:'( )', x:'()',          t:'op'},
                {l:'7',   x:'7',           t:'num'},
                {l:'8',   x:'8',           t:'num'},
                {l:'9',   x:'9',           t:'num'},
                {l:'÷',   x:'\\div',       t:'op'},
            ],[
                {l:'>',   x:'>',           t:'rel'},
                {l:'<',   x:'<',           t:'rel'},
                {l:'≥',   x:'\\geq',       t:'rel'},
                {l:'≤',   x:'\\leq',       t:'rel'},
                {l:'≠',   x:'\\neq',       t:'rel'},
                {l:'|x|', x:'\\left|\\right|', t:'op', c:7},
                {l:'4',   x:'4',           t:'num'},
                {l:'5',   x:'5',           t:'num'},
                {l:'6',   x:'6',           t:'num'},
                {l:'×',   x:'\\times',     t:'op'},
            ],[
                {l:'√',   x:'\\sqrt{}',    t:'fn', c:1},
                {l:'∛',   x:'\\sqrt[3]{}', t:'fn', c:1},
                {l:'x²',  x:'^{2}',        t:'fn'},
                {l:'xⁿ',  x:'^{}',         t:'fn', c:1},
                {l:'log', x:'\\log ',      t:'fn'},
                {l:'ln',  x:'\\ln ',       t:'fn'},
                {l:'1',   x:'1',           t:'num'},
                {l:'2',   x:'2',           t:'num'},
                {l:'3',   x:'3',           t:'num'},
                {l:'−',   x:'-',           t:'op'},
            ],[
                {l:'π',   x:'\\pi',        t:'const'},
                {l:'x!',  x:'!',           t:'fn'},
                {l:'Σ',   x:'\\sum',       t:'fn'},
                {l:'Π',   x:'\\prod',      t:'fn'},
                {l:'∞',   x:'\\infty',     t:'const'},
                {l:'°',   x:'^{\\circ}',   t:'fn'},
                {l:'0',   x:'0',           t:'num'},
                {l:'.',   x:'.',           t:'num'},
                {l:'=',   x:'=',           t:'rel'},
                {l:'+',   x:'+',           t:'op'},
            ],
        ];
        var MK_ROW5 = [
            {l:'a/b',  t:'frac',    action:'frac-builder'},
            {l:'CE',   t:'ce'},
            {l:'⌫',    t:'backspace'},
            {l:'→',    t:'move-right'},
            {l:'OK',   t:'ok'},
        ];

        var latexModeActive = false;

        function buildMathKeyboard() {
            var kb = document.getElementById('formula-keyboard');
            if (!kb) return;
            kb.innerHTML = '';
            MK_ROWS.forEach(function(row) {
                var rowDiv = document.createElement('div');
                rowDiv.className = 'math-key-row';
                row.forEach(function(k) {
                    var btn = document.createElement('button');
                    btn.className = 'math-key type-' + k.t;
                    btn.textContent = k.l;
                    btn.title = k.x || '';
                    btn.type = 'button';
                    (function(kk){ btn.addEventListener('click', function() { mkInsert(kk.x, kk.c); }); })(k);
                    rowDiv.appendChild(btn);
                });
                kb.appendChild(rowDiv);
            });
            var row5 = document.createElement('div');
            row5.className = 'math-key-row5';
            MK_ROW5.forEach(function(k) {
                var btn = document.createElement('button');
                btn.className = 'math-key type-' + k.t;
                btn.textContent = k.l;
                btn.type = 'button';
                (function(kk){
                    btn.addEventListener('click', function() {
                        if (kk.t === 'ce')                      { mkCE(); }
                        else if (kk.t === 'backspace')          { mkBackspace(); }
                        else if (kk.t === 'move-right')         { mkMoveRight(); }
                        else if (kk.t === 'ok')                 { insertFormulaIntoInput(); }
                        else if (kk.action === 'frac-builder')  { openFracBuilder(); }
                        else { mkInsert(kk.x, kk.c); }
                    });
                })(k);
                row5.appendChild(btn);
            });
            kb.appendChild(row5);
        }

        function mkInsert(latex, cursorOffset) {
            var input = document.getElementById('formula-latex-input');
            var pos = input.selectionStart !== null ? input.selectionStart : input.value.length;
            var val = input.value;
            input.value = val.substring(0, pos) + latex + val.substring(pos);
            var newPos = cursorOffset ? pos + latex.length - cursorOffset : pos + latex.length;
            input.selectionStart = input.selectionEnd = newPos;
            updateFormulaPreview();
        }

        function mkCE() {
            var input = document.getElementById('formula-latex-input');
            input.value = '';
            updateFormulaPreview();
        }

        function mkBackspace() {
            var input = document.getElementById('formula-latex-input');
            var pos = input.selectionStart;
            var val = input.value;
            if (pos > 0) {
                input.value = val.substring(0, pos - 1) + val.substring(pos);
                input.selectionStart = input.selectionEnd = pos - 1;
            }
            updateFormulaPreview();
        }

        function mkMoveRight() {
            var input = document.getElementById('formula-latex-input');
            var pos = input.selectionStart;
            var val = input.value;
            var nextClose = val.indexOf('}', pos);
            input.selectionStart = input.selectionEnd = nextClose !== -1 ? nextClose + 1 : val.length;
        }

        function toggleLatexMode() {
            latexModeActive = !latexModeActive;
            var section = document.getElementById('formula-latex-section');
            var btn = document.getElementById('btn-toggle-latex-mode');
            section.style.display = latexModeActive ? '' : 'none';
            btn.classList.toggle('active', latexModeActive);
            if (latexModeActive) document.getElementById('formula-latex-input').focus();
        }

        function resetFormulaModal() {
            latexModeActive = false;
            document.getElementById('formula-latex-input').value = '';
            document.getElementById('formula-display-mode').checked = false;
            var section = document.getElementById('formula-latex-section');
            if (section) section.style.display = 'none';
            var btn = document.getElementById('btn-toggle-latex-mode');
            if (btn) btn.classList.remove('active');
            var fracBuilder = document.getElementById('mk-frac-builder');
            if (fracBuilder) fracBuilder.style.display = 'none';
            var kb = document.getElementById('formula-keyboard');
            if (kb) kb.style.display = '';
            updateFormulaPreview();
        }

        function openFracBuilder() {
            document.getElementById('formula-keyboard').style.display = 'none';
            document.getElementById('mk-frac-builder').style.display = '';
            document.getElementById('mk-frac-num').value = '';
            document.getElementById('mk-frac-den').value = '';
            updateFracPreview();
            setTimeout(function() { document.getElementById('mk-frac-num').focus(); }, 50);
        }

        function closeFracBuilder() {
            document.getElementById('mk-frac-builder').style.display = 'none';
            document.getElementById('formula-keyboard').style.display = '';
        }

        function updateFracPreview() {
            var num = document.getElementById('mk-frac-num').value || '\\square';
            var den = document.getElementById('mk-frac-den').value || '\\square';
            var preview = document.getElementById('mk-frac-preview');
            try {
                preview.innerHTML = katex.renderToString('\\dfrac{' + num + '}{' + den + '}', { throwOnError: false, displayMode: false });
            } catch(e) {
                preview.innerHTML = '<span style="font-size:0.8rem;color:var(--danger);">⚠ Error</span>';
            }
        }

        function insertFrac() {
            var num = document.getElementById('mk-frac-num').value.trim();
            var den = document.getElementById('mk-frac-den').value.trim();
            var latex = '\\frac{' + (num || '\\square') + '}{' + (den || '\\square') + '}';
            closeFracBuilder();
            mkInsert(latex, 0);
        }

        function toast(msg, type = 'success') {
            const el = document.getElementById('toast');
            el.textContent = msg;
            el.className = type === 'success' ? 'toast-success' : 'toast-error';
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 3500);
        }

        function setStep(n) {
            const steps = ['ind-step1', 'ind-step2', 'ind-step3', 'ind-step4'];
            const lines = ['line1', 'line2', 'line3'];
            steps.forEach((id, i) => {
                const el = document.getElementById(id);
                el.className = 'step' + (i + 1 === n ? ' active' : i + 1 < n ? ' done' : '');
            });
            lines.forEach((id, i) => {
                document.getElementById(id).className = 'step-line' + (i + 1 < n ? ' done' : '');
            });
        }

        function hideGeneratedStep() {
            const genSection = document.getElementById('section-generate');
            const regenWarn = document.getElementById('regen-warning');
            
            // If test was already generated, any change requires warning
            if (testGeneratedAtLeastOnce) {
                if (regenWarn) regenWarn.style.setProperty('display', 'flex', 'important');
            }

            // Hide the actual generation result section if it was visible
            if (genSection.style.display !== 'none' && genSection.style.display !== '') {
                genSection.style.display = 'none';
                
                // Reset step indicator to Step 3 if it was at 4
                const step4 = document.getElementById('ind-step4');
                if (step4 && step4.classList.contains('active')) {
                    setStep(3);
                }
            }
        }

        /* ── ASIGNATURA OTRA ── */
        function toggleOtra(sel) {
            const input = document.getElementById('cfg-asignatura-otra');
            input.style.display = sel.value === 'Otra' ? 'block' : 'none';
            if (sel.value !== 'Otra') input.value = '';
        }

        /* ── DRAG & DROP ── */
        const dropZone = document.getElementById('drop-zone');
        dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) processFile(file);
        });

        /* ── FILE INPUT ── */
        document.getElementById('excel-input').addEventListener('change', function () {
            if (this.files[0]) processFile(this.files[0]);
        });

        function processFile(file) {
            if (!file.name.match(/\.xlsx?$/i)) {
                toast('⚠️ Solo se aceptan archivos .xlsx o .xls', 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const wb = XLSX.read(e.target.result, { type: 'array' });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    const rows = XLSX.utils.sheet_to_json(ws, { defval: '' });

                    // Normalize keys (trim spaces & case)
                    const norm = rows.map(r => {
                        const obj = {};
                        Object.keys(r).forEach(k => { obj[k.trim()] = String(r[k]).trim(); });
                        return obj;
                    });

                    // Validate required columns
                    const required = ['Pregunta', 'Alternativa_A', 'Alternativa_B', 'Alternativa_C', 'Alternativa_D', 'Respuesta_Correcta'];
                    const firstRow = norm[0] || {};
                    const missing = required.filter(c => !(c in firstRow));
                    if (missing.length) {
                        toast(`❌ Faltan columnas: ${missing.join(', ')}`, 'error');
                        return;
                    }

                    questions = norm.filter(r => r['Pregunta']);
                    if (!questions.length) { toast('⚠️ No se encontraron preguntas en el archivo.', 'error'); return; }

                    // Auto-detect scoring mode from Excel
                    const hasPuntaje = questions.some(q => q['Puntaje'] && String(q['Puntaje']).trim() !== '');
                    if (hasPuntaje) {
                        document.getElementById('cfg-modo-puntaje').value = 'manual';
                        toggleScoringMode();
                        toast('📊 Columna "Puntaje" detectada → modo manual activado');
                    }

                    // Show file name
                    const label = document.getElementById('file-name-label');
                    label.textContent = `✅ ${file.name} — ${questions.length} pregunta(s) detectada(s)`;
                    label.style.display = 'block';

                    // Go to step 2
                    document.getElementById('step-config').style.display = 'block';
                    document.getElementById('step-preview').style.display = 'none';
                    hideGeneratedStep();
                    document.getElementById('step-config').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    setStep(2);
                    toast(`✅ ${questions.length} preguntas cargadas correctamente`);
                } catch (err) {
                    toast('❌ Error al leer el archivo Excel: ' + err.message, 'error');
                }
            };
            reader.readAsArrayBuffer(file);
        }

        /* ── IMAGE HELPERS ── */
        function handleImageUpload(file, idx) {
            if (!file || !file.type.startsWith('image/')) {
                toast('⚠️ Solo se aceptan archivos de imagen (JPG, PNG, GIF, WebP)', 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                questions[idx]['_imagen'] = e.target.result; // base64 data URI original sin pérdida
                renderImgCell(idx);
                hideGeneratedStep();
                toast('🖼️ Imagen cargada para la pregunta ' + (idx + 1));
            };
            reader.readAsDataURL(file);
        }

        function renderImgCell(idx) {
            const cell = document.getElementById('img-cell-' + idx);
            if (!cell) return;
            const src = questions[idx]['_imagen'];
            if (src) {
                cell.innerHTML = `
                    <div class="img-cell">
                        <img class="img-thumb" src="${src}" title="Pregunta ${idx + 1}" onclick="document.getElementById('img-input-${idx}').click()">
                        <button class="img-remove-btn" onclick="removeImg(${idx})">✕ Quitar</button>
                    </div>`;
            } else {
                cell.innerHTML = `
                    <div style="display:flex; flex-direction: column; gap:0.4rem; align-items:center;">
                        <label class="img-upload-btn" for="img-input-${idx}">📷 Subir imagen desde el PC</label>
                        <button type="button" class="img-search-btn" onclick="openImageSearch(${idx}, 'preview')">🖼️ Buscar imagen online</button>
                    </div>
                    <input type="file" id="img-input-${idx}" accept="image/*" style="display:none"
                        onchange="handleImageUpload(this.files[0], ${idx}); this.value='';">`;
            }
        }

        function removeImg(idx) {
            delete questions[idx]['_imagen'];
            renderImgCell(idx);
            hideGeneratedStep();
            toast('🗑️ Imagen eliminada de la pregunta ' + (idx + 1));
        }

        /* ── PREVIEW ── */
        document.getElementById('btn-preview').addEventListener('click', () => {
            const body = document.getElementById('preview-body');
            body.innerHTML = '';
            const isManual = getScoringMode() === 'manual';
            const puntajeMax = parseInt(document.getElementById('cfg-puntaje').value) || questions.length;
            questions.forEach((q, i) => {
                const correct = escHtml((q['Respuesta_Correcta'] || '').toUpperCase());
                let puntajeCell;
                if (isManual) {
                    const pts = parseFloat(q['Puntaje']) || 1;
                    puntajeCell = `<input type="number" class="pts-input" value="${pts}" min="0" max="999" step="0.5" style="width:60px;text-align:center;padding:0.3rem;border-radius:6px;border:1.5px solid var(--border);background:var(--surface);font-size:0.82rem;font-weight:600;font-family:'Inter',sans-serif;" onchange="updateQuestionPoints(${i}, this.value)" oninput="updateQuestionPoints(${i}, this.value)">`;
                } else {
                    const valPerQ = (puntajeMax / questions.length).toFixed(1);
                    puntajeCell = `<span style="color:var(--text-muted);font-size:0.8rem;">${valPerQ}</span>`;
                }
                body.innerHTML += `
        <tr>
          <td class="col-num">${i + 1}</td>
          <td style="white-space:normal; max-width:280px;">${escHtml(q['Pregunta'])}</td>
          <td>${escHtml(q['Alternativa_A']) || '—'}</td>
          <td>${escHtml(q['Alternativa_B']) || '—'}</td>
          <td>${escHtml(q['Alternativa_C']) || '—'}</td>
          <td>${escHtml(q['Alternativa_D']) || '—'}</td>
          <td style="text-align:center"><span class="col-correct">${correct}</span></td>
          <td style="text-align:center">${puntajeCell}</td>
          <td style="color:var(--text-muted)">${escHtml(q['Habilidad']) || '—'}</td>
          <td style="text-align:center" id="img-cell-${i}"></td>
        </tr>`;
            });
            // Render image cells after DOM is updated
            questions.forEach((q, i) => renderImgCell(i));
            document.getElementById('preview-count').textContent = `${questions.length} preguntas`;
            // Update puntaje total indicator
            updatePuntajeTotalIndicator();
            document.getElementById('step-preview').style.display = 'block';
            document.getElementById('step-preview').scrollIntoView({ behavior: 'smooth', block: 'start' });
            setStep(3);
        });

        function updateQuestionPoints(idx, val) {
            questions[idx]['Puntaje'] = parseFloat(val) || 1;
            updatePuntajeTotalIndicator();
            hideGeneratedStep();
        }

        function updatePuntajeTotalIndicator() {
            const indicator = document.getElementById('puntaje-total-indicator');
            const isManual = getScoringMode() === 'manual';
            if (isManual) {
                const total = calcPuntajeMaxManual();
                document.getElementById('puntaje-total-value').textContent = total;
                indicator.style.display = 'block';
            } else {
                indicator.style.display = 'none';
            }
        }

        /* ── BACK BUTTONS ── */
        document.getElementById('btn-back-1').addEventListener('click', () => {
            document.getElementById('step-config').style.display = 'none';
            document.getElementById('step-preview').style.display = 'none';
            document.getElementById('section-generate').style.display = 'none';
            setStep(1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        document.getElementById('btn-back-2').addEventListener('click', () => {
            document.getElementById('step-preview').style.display = 'none';
            document.getElementById('section-generate').style.display = 'none';
            setStep(2);
            document.getElementById('step-config').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        /* ── GENERATE ── */
        document.getElementById('btn-generate').addEventListener('click', () => {
            const titulo = document.getElementById('cfg-titulo').value || 'Prueba';
            const selAsig = document.getElementById('cfg-asignatura');
            const isMath = selAsig.value === 'Matemáticas' || mathModeEnabled
                || questions.some(q => /\$/.test(q['Pregunta'] + (q['Alternativa_A'] || '') + (q['Alternativa_B'] || '')));
            const katexHeadHtml = isMath
                ? '  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css"\/>\n  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"><\/script>\n  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"><\/script>'
                : '';
            const katexBodyHtml = isMath
                ? '<script>document.addEventListener("DOMContentLoaded",function(){if(typeof renderMathInElement!=="undefined"){renderMathInElement(document.body,{delimiters:[{left:"$$",right:"$$",display:true},{left:"$",right:"$",display:false}],throwOnError:false});}});<\/script>'
                : '';
            const asignatura = selAsig.value === 'Otra'
                ? (document.getElementById('cfg-asignatura-otra').value.trim() || 'Otra')
                : selAsig.value;
            const curso = document.getElementById('cfg-curso').value || '';
            const docente = document.getElementById('cfg-docente').value || '';
            const tiempo = document.getElementById('cfg-tiempo').value || 45;
            const mostrarResp = document.getElementById('cfg-mostrar-resp').value;
            const shuffleAlts = document.getElementById('cfg-shuffle-alts').value === 'si';
            const shuffleMode = document.getElementById('cfg-shuffle-qs').value;
            const instrucciones = document.getElementById('cfg-instrucciones').value || 'Lee atentamente cada pregunta y marca la alternativa correcta.';

            // --- INICIO: Lógica de agrupación y mezcla ---
            // 1. Agrupar preguntas por texto de lectura. Las que no tienen texto forman su propio grupo.
            const questionGroups = [];
            let currentPassage = '___UNIQUE_INITIAL_VALUE___';
            let currentGroup = null;
            questions.forEach(q => {
                const passage = q['Texto_Lectura'] || '';
                if (passage !== currentPassage) {
                    currentPassage = passage;
                    currentGroup = { passage: passage, questions: [] };
                    questionGroups.push(currentGroup);
                }
                currentGroup.questions.push(q); // Agrega el objeto de pregunta original al grupo
            });

            // 2. Mezclar los grupos de secciones (si corresponde)
            if (shuffleMode === 'all') {
                for (let i = questionGroups.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [questionGroups[i], questionGroups[j]] = [questionGroups[j], questionGroups[i]];
                }
            }

            // 3. Mezclar las preguntas DENTRO de cada grupo (si corresponde)
            if (shuffleMode === 'all' || shuffleMode === 'within') {
                questionGroups.forEach(group => {
                    if (group.questions.length > 1) {
                        for (let i = group.questions.length - 1; i > 0; i--) {
                            const j = Math.floor(Math.random() * (i + 1));
                            [group.questions[i], group.questions[j]] = [group.questions[j], group.questions[i]];
                        }
                    }
                });
            }

            // 4. Aplanar los grupos en un array final de preguntas ya ordenadas
            const finalQuestions = [].concat(...questionGroups.map(g => g.questions));
            // --- FIN: Lógica de agrupación y mezcla ---

            const scoringMode = getScoringMode();
            const exigencia = parseFloat(document.getElementById('cfg-dificultad').value) || 0.6;

            const questionsData = jsonForScript(finalQuestions.map(q => ({
                p: q['Pregunta'],
                a: q['Alternativa_A'] || '',
                b: q['Alternativa_B'] || '',
                c: q['Alternativa_C'] || '',
                d: q['Alternativa_D'] || '',
                r: (q['Respuesta_Correcta'] || '').toUpperCase(),
                h: q['Habilidad'] || '',
                img: q['_imagen'] || '',
                pas: q['Texto_Lectura'] || '',
                pts: scoringMode === 'manual' ? (parseFloat(q['Puntaje']) || 1) : 0
            })));

            // Calculate puntajeMax based on scoring mode
            let puntajeMax;
            if (scoringMode === 'manual') {
                puntajeMax = 0;
                finalQuestions.forEach(q => { puntajeMax += (parseFloat(q['Puntaje']) || 1); });
            } else {
                puntajeMax = parseInt(document.getElementById('cfg-puntaje').value) || questions.length;
            }
            
            generatedHTML = `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>${escHtml(titulo)}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"><\/script>
  ${katexHeadHtml}
  <style>
    :root {
      --navy:#2c3e50; --navy-dark:#1a252f; --accent:#60a5fa;
      --bg:#f8fafc; --surface:#ffffff; --surface2:#f1f5f9;
      --border:#e2e8f0; --text:#334155; --text-muted:#64748b;
      --success:#10b981; --danger:#ef4444; --gold:#f59e0b;
    }
    body.dark-mode {
      --bg:#0f172a; --surface:#1e293b; --surface2:#334155;
      --text:#f8fafc; --text-muted:#94a3b8; --border:#334155;
      --navy:#60a5fa; --navy-dark:#3b82f6;
    }
    .theme-toggle {
      position: fixed; top: 1rem; right: 1rem;
      background: var(--surface); border: 1px solid var(--border);
      color: var(--text); font-size: 1.25rem;
      width: 45px; height: 45px; border-radius: 50%;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
      z-index: 1000; transition: all 0.2s ease;
    }
    .theme-toggle:hover { transform: scale(1.1); }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding-left: 280px; user-select:none; -webkit-user-select:none; }
    #anti-cheat-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#000; color:#fff; z-index:99999; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:2rem; }
    .cheat-warning { font-size:4rem; margin-bottom:1rem; }
    .cheat-desc { font-size:1.5rem; color:#ef4444; font-weight:800; max-width:600px; line-height:1.4; }
    .cheat-count { font-size:1.2rem; color:#fca5a5; margin-top:1rem; font-weight:600; }
    header {
      background:#ffffff;
      padding:1rem 2rem; border-bottom:1px solid var(--border);
      box-shadow:0 2px 10px rgba(0,0,0,0.02); position:fixed; top:0; right: 0; left: 280px; z-index:50;
    }
    .header-inner { max-width:1150px; margin:0 auto; display:flex; align-items:center; gap:1rem; }
    .logo-img { width:48px; height:auto; max-height:48px; flex-shrink:0; object-fit:contain; }
    .htext h1 { font-size:1.1rem; font-weight:800; line-height:1.1; color:var(--navy); }
    .htext p  { font-size:0.75rem; color:var(--text-muted); margin-top:2px; }
    .timer-box { margin-left:auto; background:rgba(96,165,250,0.08); border:1px solid rgba(96,165,250,0.15); border-radius:10px; padding:0.4rem 0.8rem; text-align:center; }
    .timer-label { font-size:0.6rem; color:var(--accent); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
    .timer-val   { font-size:1.1rem; font-weight:800; color:var(--accent); font-feature-settings:'tnum'; }
    main { max-width:1100px; margin: 100px auto 3rem; padding:0 1.5rem; }
    /* Student Name */
    .student-box {
      background:var(--surface); border:2px solid var(--accent); border-radius:16px;
      padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem;
    }
    .student-box label { font-size:0.85rem; font-weight:700; color:#3b82f6; white-space:nowrap; }
    .student-box input {
      flex:1; background:var(--surface2); border:1px solid var(--border); border-radius:10px;
      padding:0.6rem 1rem; font-size:1rem; color:var(--text); font-family:'Inter',sans-serif; outline:none;
      transition:border-color 0.2s;
    }
    .student-box input:focus { border-color:var(--accent); }
    /* Meta */
    .meta { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; flex-wrap:wrap; gap:1rem; }
    .meta-item { flex:1; min-width:140px; }
    .meta-item .lbl { font-size:0.7rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; }
    .meta-item .val { font-size:0.95rem; font-weight:700; color:var(--text); margin-top:2px; }
    .instrucciones { background:rgba(59,130,246,0.06); border:1px solid rgba(59,130,246,0.2); border-radius:12px; padding:1rem 1.25rem; margin-bottom:2rem; font-size:0.85rem; color:#475569; line-height:1.6; }
    .instrucciones strong { color:#1a4a7a; }
    /* Questions */
    .question-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:1.5rem; margin-bottom:1.25rem; transition:border-color .2s; }
    .question-card.answered { border-color:rgba(59,130,246,0.5); }
    .question-card.correct  { border-color:var(--success)!important; background:rgba(16,185,129,0.05)!important; }
    .question-card.wrong    { border-color:var(--danger)!important;  background:rgba(239,68,68,0.05)!important; }
    .question-card.has-passage {
      border-left: 5px solid #2563eb;
      background: rgba(59,130,246,0.08);
      box-shadow: inset 0 0 0 1px rgba(37,99,235,0.16);
    }
    .question-card.has-passage .q-text {
      color: #1d4ed8;
    }
    .question-card.has-passage .q-alts .alt-label {
      border-color: rgba(37,99,235,0.2);
      background: rgba(59,130,246,0.04);
    }
    .q-header { display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem; flex-wrap:wrap; }
    .q-num    { font-size:.75rem; font-weight:700; color:#1a4a7a; text-transform:uppercase; letter-spacing:.05em; }
    .q-passage-badge { background:#2563eb; color:#fff; font-size:0.72rem; font-weight:800; padding:0.35rem 0.75rem; border-radius:999px; text-transform:uppercase; letter-spacing:0.06em; display:inline-flex; align-items:center; border:1px solid rgba(37,99,235,0.3); }

    .q-skill  { background:rgba(217,119,6,0.1); border:1px solid rgba(217,119,6,0.25); border-radius:6px; padding:.15rem .6rem; font-size:.7rem; color:#d97706; font-weight:600; }
    .q-text   { font-size:1.3rem; font-weight:500; line-height:1.6; margin-bottom:1rem; color:var(--text); }
    .q-passage { background:rgba(96,165,250,0.06); border-left:4px solid var(--accent); padding:1rem 1.25rem; margin-bottom:1.25rem; font-size:0.95rem; line-height:1.6; color:var(--text); border-radius:0 12px 12px 0; max-height:300px; overflow-y:auto; white-space:pre-wrap; }
    .dark-mode .q-passage { background:rgba(96,165,250,0.04); border-left-color:var(--navy); }
    .q-img    { max-width:100%; max-height:320px; width:auto; border-radius:10px; border:1px solid var(--border); display:block; object-fit:contain; background:#f8fafc; cursor:pointer; }
    .img-wrapper { position:relative; display:inline-block; margin-bottom:1rem; }
    .img-zoom-icon { position:absolute; top:10px; right:10px; background:var(--accent); color:white; width:44px; height:44px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.3); opacity:0.95; transition:all 0.2s; font-size:1.4rem; z-index:10; }
    .img-zoom-icon:hover { opacity:1; transform:scale(1.15); box-shadow:0 6px 16px rgba(0,0,0,0.4); background:#2563eb; }
    /* Modal Zoom */
    .img-modal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:hidden; background-color:rgba(0,0,0,0.85); backdrop-filter:blur(5px); flex-direction:column; align-items:center; justify-content:center; }
    .img-modal-content { display:block; width:90vw; max-height:90vh; object-fit:contain; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,0.5); transform-origin:center center; transition:transform 0.1s ease-out; cursor:grab; margin:auto; }
    .img-modal-content:active { cursor:grabbing; }
    .img-close { position:absolute; top:20px; right:30px; color:#f1f1f1; font-size:40px; font-weight:bold; cursor:pointer; z-index:10000; text-shadow:0 2px 4px rgba(0,0,0,0.5); }
    .img-close:hover { color:#bbb; }
    .img-zoom-controls { position:fixed; bottom:30px; left:50%; transform:translateX(-50%); display:flex; gap:10px; background:rgba(25,25,25,0.9); padding:10px 20px; border-radius:30px; box-shadow:0 5px 20px rgba(0,0,0,0.4); z-index:10000; align-items:center; }
    .zoom-btn { background:#444; border:none; color:white; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; transition:background 0.2s; }
    .zoom-btn:hover { background:#666; }
    .zoom-val { color:white; font-size:.9rem; font-weight:600; min-width:50px; text-align:center; user-select:none; }
    .q-alts   { display:flex; flex-direction:column; gap:.6rem; }
    .alt-label { display:flex; align-items:flex-start; gap:.75rem; cursor:pointer; padding:.7rem .9rem; border-radius:10px; border:1.5px solid var(--border); background:var(--surface2); transition:all .2s; }
    .alt-label:hover { border-color:var(--accent); background:rgba(59,130,246,0.08); }
    .alt-label input[type=radio] { display:none; }
    .alt-label.selected    { border-color:var(--accent); background:rgba(59,130,246,0.12); }
    .alt-label.correct-ans { border-color:var(--success)!important; background:rgba(16,185,129,0.15)!important; }
    .alt-label.wrong-ans   { border-color:var(--danger)!important;  background:rgba(239,68,68,0.15)!important; }
    .alt-circle { width:34px;height:34px;border-radius:50%;background:rgba(59,130,246,0.12);display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:800;color:var(--accent);flex-shrink:0; }
    .alt-label.selected .alt-circle    { background:var(--accent);  color:#fff; }
    .alt-label.correct-ans .alt-circle { background:var(--success); color:#fff; }
    .alt-label.wrong-ans .alt-circle   { background:var(--danger);  color:#fff; }
    .alt-text { font-size:1.2rem; line-height:1.5; padding-top:2px; }
    /* Results */
    #results-panel { display:none; background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:2.5rem; margin-top:2rem; }
    .results-top { display:flex; gap:2rem; align-items:center; flex-wrap:wrap; margin-bottom:2rem; }
    .score-circle { width:120px;height:120px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;border:4px solid var(--success);background:rgba(16,185,129,0.1);flex-shrink:0; }
    .score-circle.fail { border-color:var(--danger); background:rgba(239,68,68,0.1); }
    .score-num { font-size:1.8rem;font-weight:800;color:var(--success);line-height:1; }
    .score-circle.fail .score-num { color:var(--danger); }
    .score-lbl { font-size:.65rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;margin-top:2px; }
    .results-info { flex:1; }
    .results-info h2 { font-size:1.2rem;font-weight:800;margin-bottom:.5rem;color:var(--text); }
    .results-info p  { font-size:.9rem;color:var(--text-muted);margin-bottom:.3rem;line-height:1.5; }
    .nota-badge { display:inline-block;font-size:2rem;font-weight:900;padding:.3rem 1rem;border-radius:12px;margin:.5rem 0; }
    .nota-verde  { background:rgba(16,185,129,0.15);color:var(--success);border:2px solid var(--success); }
    .nota-amarilla{ background:rgba(245,158,11,0.15);color:var(--gold);border:2px solid var(--gold); }
    .nota-roja   { background:rgba(239,68,68,0.15);color:var(--danger);border:2px solid var(--danger); }
    
    .btn-informe {
      margin-top:1rem; background:linear-gradient(135deg,#60a5fa,#3b82f6);
      color:#fff;border:none;padding:.75rem 1.5rem;border-radius:12px;
      font-size:.9rem;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;
      box-shadow:0 4px 20px rgba(96,165,250,0.3);transition:all .25s;display:inline-flex;align-items:center;gap:.5rem;
    }
    .btn-informe:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(96,165,250,0.4); }
    .btn-retry {
      margin-top:1rem; background:linear-gradient(135deg,#10b981,#059669);
      color:#fff;border:none;padding:.75rem 1.5rem;border-radius:12px;
      font-size:.9rem;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;
      box-shadow:0 4px 20px rgba(16,185,129,0.3);transition:all .25s;display:inline-flex;align-items:center;gap:.5rem;
      margin-left: 0.5rem;
    }
    .btn-retry:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(44,62,80,0.3); }
    /* Submit bar (Sidebar) */
    .submit-bar { position:fixed;top:0;left:0;bottom:0;width:280px;background:#ffffff;border-right:1px solid var(--border);padding:2rem 1.5rem;display:flex;flex-direction:column;z-index:200;box-shadow:2px 0 15px rgba(0,0,0,0.03);overflow-y:auto;gap:1.5rem; }
    .sidebar-logo { text-align:center; margin-bottom: 0.5rem; }
    .sidebar-logo img { width: 80px; height: auto; margin-bottom: 0.8rem; }
    .sidebar-logo h2 { font-size: 0.95rem; color: var(--navy); font-weight: 800; line-height: 1.2; }
    .progress-wrap { display:flex;flex-direction:column;gap:1rem;flex:1; }
    .progress-top  { display:flex;flex-direction:column;gap:.5rem; }
    .answered-badge { font-size:1.1rem;font-weight:800;color:var(--navy); }
    .answered-badge .total { color:var(--text-muted);font-weight:600; font-size:0.85rem; }
    .progress-bar  { width:100%;height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden; }
    .progress-fill { height:100%;background:linear-gradient(90deg,var(--accent),#93c5fd);border-radius:5px;transition:width .4s ease; }
    .pending-info  { font-size:.8rem;color:var(--text-muted);font-weight:600;display:flex;flex-direction:column;gap:.5rem; }
    .pending-chips { display:flex;flex-wrap:wrap;gap:.4rem; }
    .p-chip { background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);border-radius:6px;padding:.3rem .55rem;font-size:.75rem;font-weight:700;cursor:pointer;transition:all .15s; min-width:32px; text-align:center; }
    .p-chip:hover { transform:scale(1.05); border-color:var(--accent); color:var(--accent); }
    .p-chip.answered { background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.3);color:#059669; }
    .all-ok-msg { font-size:.85rem;color:var(--success);font-weight:700; text-align:center; padding: 1rem 0; }
    .btn-submit { background:linear-gradient(135deg,var(--accent),#3b82f6);color:#fff;border:none;padding:1rem;border-radius:12px;font-size:1rem;font-weight:700;cursor:pointer;box-shadow:0 4px 15px rgba(96,165,250,0.3);transition:all .25s;width:100%;margin-top:auto; }
    .btn-submit:hover    { transform:translateY(-2px); box-shadow:0 6px 20px rgba(96,165,250,0.4); }
    .btn-submit:disabled { opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;background:var(--text-muted); }
    
    @media(max-width:850px) {
      body { padding-left: 0; padding-bottom: 120px; }
      header { left: 0; width: 100%; }
      .submit-bar { position:fixed;top:auto;bottom:0;left:0;width:100%;height:auto;flex-direction:row;align-items:center;padding:1rem;border-right:none;border-top:1px solid var(--border); box-shadow:0 -4px 15px rgba(0,0,0,0.05); }
      .sidebar-logo { display: none; }
      .btn-submit { width: auto; margin-top: 0; padding: 0.8rem 1.5rem; }
      .results-top { flex-direction:column; }
    }
  <\/style>
</head>
<body>
<button id="theme-toggle" class="theme-toggle" title="Cambiar tema">🌙</button>
<div id="anti-cheat-overlay">
  <div class="cheat-warning">⚠️</div>
  <div class="cheat-desc">¡Atención! Has salido de la pestaña de la prueba o intentado cambiar de ventana. Esto se considera una falta a la integridad de la evaluación.</div>
  <div class="cheat-count">Advertencias registradas: <span id="cheat-count-val">0</span></div>
  <div style="margin-top:2rem;font-size:1rem;color:#94a3b8;">Vuelve a la prueba para continuar.</div>
</div>
<header>
  <div class="header-inner">
    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffffff'><path d='M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z'/></svg>" class="logo-img" alt="Logo"/>
    <div class="htext">
      <h1>${escHtml(titulo)}</h1>
      <p>Pruebas educativas</p>
    </div>
    <div class="timer-box">
      <div class="timer-label">Tiempo</div>
      <div class="timer-val" id="timer">--:--</div>
    </div>
  </div>
</header>

<main>
  <!-- NOMBRE ALUMNO -->
  <div class="student-box">
    <label>\u{1F464} Nombre del alumno:</label>
    <input type="text" id="student-name" placeholder="Escribe tu nombre completo aquí..." />
  </div>

  <div class="meta">
    <div class="meta-item"><div class="lbl">Asignatura</div><div class="val">${escHtml(asignatura)}</div></div>
    <div class="meta-item"><div class="lbl">Curso</div><div class="val">${escHtml(curso || '\u2014')}</div></div>
    <div class="meta-item"><div class="lbl">Docente</div><div class="val">${escHtml(docente || '\u2014')}</div></div>
    <div class="meta-item"><div class="lbl">Preguntas</div><div class="val">${questions.length}</div></div>
    <div class="meta-item"><div class="lbl">Puntaje m\u00e1x.</div><div class="val">${puntajeMax} pts</div></div>
    <div class="meta-item"><div class="lbl">Tiempo</div><div class="val">${tiempo} min</div></div>
  </div>

  <div class="instrucciones">
    <strong>\u{1F4CC} Instrucciones:</strong>&nbsp;${escHtml(instrucciones)}
  </div>

  <div id="questions-container"></div>

  <div id="results-panel">
    <div class="results-top">
      <div class="score-circle" id="score-circle">
        <div class="score-num" id="score-num">0</div>
        <div class="score-lbl">Correctas</div>
      </div>
      <div class="results-info">
        <h2 id="results-msg"></h2>
        <p id="results-sub"></p>
        <div class="nota-badge" id="nota-badge"></div>
        <div id="analytics-container"></div>
        <br/>
        <button class="btn-informe" id="btn-informe">\u{1F4CB} Retroalimentación Alumno</button>
        <button class="btn-retry" id="btn-reintentar">\uD83D\uDD04 Realizar nuevamente</button>

        <div id="download-section" style="margin-top: 1.5rem; display: none;">
          <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.5rem; font-weight:600;">\u{1F4BE} Guardar resultados para entregar al profesor:</p>
          <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <button id="btn-download-results" class="btn-informe" style="margin:0; padding:0.8rem 1.4rem; white-space:nowrap; background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 20px rgba(245,158,11,0.3);">\u{1F4E5} Descargar Archivo de Resultados</button>
          </div>
          <p id="download-status" style="font-size:0.82rem; margin-top:0.5rem; color:var(--text-muted); font-weight:600;">Descarga el archivo y guárdalo en la carpeta de entregas o red compartida.</p>
        </div>
      </div>
    </div>
  </div>
</main>

<div class="submit-bar">
  <div class="sidebar-logo">
    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%231a4a7a'><path d='M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z'/></svg>" alt="Logo"/>
    <h2>Pruebas educativas</h2>
  </div>
  <div class="progress-wrap">
    <div class="progress-top">
      <span class="answered-badge"><span id="answered-num">0</span><span class="total"> / ${questions.length} respondidas</span></span>
    </div>
    <div class="progress-bar"><div class="progress-fill" id="progress-fill" style="width:0%"></div></div>
    <div class="pending-info" id="pending-info">
      <span id="pending-label" style="display:none; color:var(--danger);">\u26A0\uFE0F Debes responder todas las preguntas para habilitar el bot\u00F3n <span style="color:var(--accent);font-weight:800;">Revisar Prueba</span>.</span>
      <span style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-top:0.5rem;">Mapa de preguntas:</span>
      <div class="pending-chips" id="pending-chips"></div>
    </div>
  </div>
  <button class="btn-submit" id="btn-submit" disabled>\u{1F50D} Revisar Prueba</button>
</div>

<!-- Modal Zoom -->
<div id="img-modal" class="img-modal">
  <span class="img-close" onclick="closeZoom()">&times;</span>
  <img class="img-modal-content" id="img-modal-content">
  <div class="img-zoom-controls">
    <button class="zoom-btn" onclick="zoomOut()" title="Alejar">-</button>
    <div class="zoom-val" id="zoom-val">100%</div>
    <button class="zoom-btn" onclick="zoomIn()" title="Acercar">+</button>
  </div>
</div>

<script>
  function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, function(m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[m];
    });
  }

  function safeImageSrc(src) {
    const value = String(src || '');
    return /^(data:image\/(?:png|jpeg|jpg|gif|webp);base64,|https?:\/\/)/i.test(value) ? value : '';
  }

  // Anti-Cheat: Prevent copy/paste/context menu
  document.addEventListener('contextmenu', e => e.preventDefault());
  document.addEventListener('copy', e => e.preventDefault());
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'c' || e.key.toLowerCase() === 'v' || e.key.toLowerCase() === 'x' || e.key.toLowerCase() === 'p')) {
      e.preventDefault();
    }
  });

  // Anti-Cheat: Tab switching / Blur
  let cheatCount = 0;
  const overlay = document.getElementById('anti-cheat-overlay');
  
  function triggerCheat() {
    if (typeof submitted !== 'undefined' && submitted) return;
    cheatCount++;
    document.getElementById('cheat-count-val').textContent = cheatCount;
    overlay.style.display = 'flex';
  }

  document.addEventListener('visibilitychange', () => { if (document.hidden) triggerCheat(); });
  window.addEventListener('blur', () => { triggerCheat(); });
  window.addEventListener('focus', () => { overlay.style.display = 'none'; });

  const QS       = ${questionsData};
  const TOTAL    = ${questions.length};
  const SHOW_ANS = ${mostrarResp === 'si' ? 'true' : 'false'};
  const SHUFFLE_ALTS = ${shuffleAlts ? 'true' : 'false'};
  const SHUFFLE_MODE = ${jsonForScript(shuffleMode)};
  const TIEMPO   = ${tiempo};
  const PMAX     = ${puntajeMax};
  const EXIG     = ${exigencia};
  const SCORING_MODE = ${jsonForScript(scoringMode)};
  const TITULO   = ${jsonForScript(titulo)};
  const ASIG     = ${jsonForScript(asignatura)};
  const CURSO    = ${jsonForScript(curso)};
  const DOCENTE  = ${jsonForScript(docente)};
  let answered   = {};
  let submitted  = false;
  let timerSecs  = TIEMPO * 60;
  let feedbackWindow;

  // Dark Mode Logic
  const themeToggle = document.getElementById('theme-toggle');
  let isDark = false;
  try { isDark = sessionStorage.getItem('theme-dark-' + TITULO.replace(/[^a-zA-Z0-9]/g, '')) === 'true'; } catch(e) {}
  if (isDark) { document.body.classList.add('dark-mode'); themeToggle.textContent = '☀️'; }
  themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    const darkNow = document.body.classList.contains('dark-mode');
    themeToggle.textContent = darkNow ? '☀️' : '🌙';
    try { sessionStorage.setItem('theme-dark-' + TITULO.replace(/[^a-zA-Z0-9]/g, ''), darkNow); } catch(e){}
  });

  // Resume progress (sessionStorage)
  const stateKey = 'testState-' + TITULO.replace(/[^a-zA-Z0-9]/g, '');
  let savedState = null;
  try { savedState = JSON.parse(sessionStorage.getItem(stateKey)); } catch(e){}
  if (savedState && !savedState.submitted && savedState.QS && savedState.QS.length === TOTAL) {
    for (let i = 0; i < TOTAL; i++) { QS[i] = savedState.QS[i]; }
    timerSecs = savedState.timerSecs;
    answered = savedState.answered || {};
    setTimeout(() => { document.getElementById('student-name').value = savedState.studentName || ''; }, 50);
  }
  const ANSWERS  = QS.map(q => q.r);

  // Zoom Logic
  let currentZoom = 1;
  let isDragging = false;
  let startX, startY, translateX = 0, translateY = 0;
  const modal = document.getElementById('img-modal');
  const modalImg = document.getElementById('img-modal-content');
  const zoomText = document.getElementById('zoom-val');

  function updateZoomTransform() {
    modalImg.style.transform = \`translate(\${translateX}px, \${translateY}px) scale(\${currentZoom})\`;
    zoomText.textContent = Math.round(currentZoom * 100) + '%';
  }
  function openZoom(src) {
    modal.style.display = 'flex';
    modalImg.src = src;
    currentZoom = 1; translateX = 0; translateY = 0;
    updateZoomTransform();
  }
  function closeZoom() { modal.style.display = 'none'; }
  function zoomIn() { currentZoom = Math.min(currentZoom + 0.25, 4); updateZoomTransform(); }
  function zoomOut() { currentZoom = Math.max(currentZoom - 0.25, 0.5); updateZoomTransform(); }
  
  modalImg.addEventListener('wheel', function(e) {
    e.preventDefault();
    if (e.deltaY < 0) zoomIn(); else zoomOut();
  });
  modalImg.addEventListener('mousedown', function(e) {
    e.preventDefault();
    isDragging = true;
    startX = e.clientX - translateX;
    startY = e.clientY - translateY;
  });
  window.addEventListener('mousemove', function(e) {
    if (!isDragging) return;
    translateX = e.clientX - startX;
    translateY = e.clientY - startY;
    updateZoomTransform();
  });
  window.addEventListener('mouseup', function() { isDragging = false; });
  
  // Cerrar al dar click afuera
  modal.addEventListener('click', function(e) {
    if (e.target === modal) closeZoom();
  });

  // Build questions
  const container = document.getElementById('questions-container');
  let currentAltsOrder = [];
  let lastPassage = '___UNIQUE_INITIAL_VALUE___';

  QS.forEach((q, i) => {
    let passageHeaderHtml = '';
    if (q.pas && q.pas !== lastPassage) {
        passageHeaderHtml = \`<div class="reading-section-header"><div class="q-passage">\${escHtml(q.pas)}</div></div>\`;
    }
    lastPassage = q.pas;

    let alts = [
      { key: 'A', text: q.a },
      { key: 'B', text: q.b },
      { key: 'C', text: q.c },
      { key: 'D', text: q.d }
    ].filter(x => x.text);

    if (SHUFFLE_ALTS) {
      if (savedState && savedState.altsOrder && savedState.altsOrder[i]) {
        alts = savedState.altsOrder[i];
      } else {
        for (let k = alts.length - 1; k > 0; k--) {
          const j = Math.floor(Math.random() * (k + 1));
          [alts[k], alts[j]] = [alts[j], alts[k]];
        }
      }
    }
    currentAltsOrder.push(alts);

    const labels = ['A', 'B', 'C', 'D'];
    const altsHTML = alts.map((alt, idx) => {
      const displayLabel = labels[idx];
      return \`<label class="alt-label" id="q\${i}-\${alt.key}"><input type="radio" name="q\${i}" value="\${alt.key}"/><span class="alt-circle">\${displayLabel}</span><span class="alt-text">\${escHtml(alt.text)}</span></label>\`;
    }).join('');
    const passageBadge = q.pas ? \`<span class="q-passage-badge">📚 Pregunta del texto</span>\` : \`\`;
    const skillHtml = q.h ? \`<span class="q-skill">\${escHtml(q.h)}</span>\` : '';
    const cardClass = q.pas ? 'question-card has-passage' : 'question-card';
    const safeImg = safeImageSrc(q.img);
    const imgHtml = safeImg ? \`
      <div class="img-wrapper">
        <img id="qimg-\${i}" src="\${escHtml(safeImg)}" class="q-img" alt="Imagen pregunta \${i+1}" onclick="openZoom(this.src)">
        <div class="img-zoom-icon" onclick="openZoom(document.getElementById('qimg-\${i}').src)" title="Ampliar imagen">\uD83D\uDD0D</div>
      </div>\` : '';
    
    container.innerHTML += passageHeaderHtml + \`
    <div class="\${cardClass}" id="qcard-\${i}">
      <div class="q-header"><span class="q-num">Pregunta \${i+1}</span>\${passageBadge}\${skillHtml}</div>
      <p class="q-text">\${escHtml(q.p)}</p>
      \${imgHtml}
      <div class="q-alts">\${altsHTML}</div>
    </div>\`;
  });

  // Save progress to sessionStorage so the student can resume after a reload
  function saveState() {
    if (submitted) {
      try { sessionStorage.removeItem(stateKey); } catch(e) {}
      return;
    }
    const state = {
      QS: QS, altsOrder: currentAltsOrder, answered: answered,
      timerSecs: timerSecs, studentName: document.getElementById('student-name').value,
      submitted: submitted
    };
    try { sessionStorage.setItem(stateKey, JSON.stringify(state)); } catch(e){}
  }
  document.getElementById('student-name').addEventListener('input', saveState);

  // Bind alt clicks
  document.querySelectorAll('.alt-label').forEach(label => {
    label.addEventListener('click', function() {
      if(submitted) return;
      const input = this.querySelector('input');
      const name  = input.name;
      const val   = input.value;
      const qIdx  = parseInt(name.replace('q',''));
      document.querySelectorAll(\`[name=\${name}]\`).forEach(r => r.closest('.alt-label').classList.remove('selected'));
      this.classList.add('selected');
      answered[qIdx] = val;
      document.getElementById('qcard-'+qIdx).classList.add('answered');
      updateProgress();
      saveState();
    });
  });

  // Restore visual state if loaded from sessionStorage
  if (savedState && Object.keys(answered).length > 0) {
    Object.keys(answered).forEach(qIdx => {
      const val = answered[qIdx];
      const label = document.getElementById('q'+qIdx+'-'+val);
      if (label) {
        label.classList.add('selected');
        label.querySelector('input').checked = true;
        document.getElementById('qcard-'+qIdx).classList.add('answered');
      }
    });
    updateProgress();
  }

  // Timer
  const timerEl = document.getElementById('timer');
  function fmtTime(s) { return Math.floor(s/60).toString().padStart(2,'0')+':' + (s%60).toString().padStart(2,'0'); }
  timerEl.textContent = fmtTime(timerSecs);
  let interval;
  function startTimer() {
    timerEl.textContent = fmtTime(timerSecs);
    timerEl.style.color = '#fca5a5';
    interval = setInterval(() => {
      timerSecs--;
      timerEl.textContent = fmtTime(timerSecs);
      if(timerSecs<=300) timerEl.style.color='#fbbf24';
      if(timerSecs % 5 === 0) saveState();
      if(timerSecs<=0){ clearInterval(interval); submitTest(true); }
    },1000);
  }
  startTimer();

  function updateProgress() {
    const n = Object.keys(answered).length;
    document.getElementById('answered-num').textContent = n;
    document.getElementById('progress-fill').style.width = (n/TOTAL*100)+'%';
    const btn = document.getElementById('btn-submit');
    const pendingInfo = document.getElementById('pending-info');
    const chipsEl = document.getElementById('pending-chips');
    const pendingLabel = document.getElementById('pending-label');
    var chipsHtml = [];
    var cards = document.getElementById('questions-container').querySelectorAll('.question-card');
    for (var k = 0; k < cards.length; k++) {
      var isAnswered = cards[k].classList.contains('answered');
      var cClass = isAnswered ? 'p-chip answered' : 'p-chip';
      var visualNum = k + 1;
      var cardId = cards[k].id;
      chipsHtml.push('<span class="' + cClass + '" onclick="document.getElementById(\\\''+cardId+'\\\').scrollIntoView({behavior:\\\'smooth\\\',block:\\\'center\\\'})" title="Ir a pregunta '+visualNum+'">'+visualNum+'</span>');
    }
    if(chipsEl) chipsEl.innerHTML = chipsHtml.join('');

    if (n >= TOTAL) {
      btn.disabled = false;
      if(pendingLabel) pendingLabel.style.display = 'none';
      var okMsg = document.getElementById('all-ok-msg');
      if(!okMsg) {
        okMsg = document.createElement('span');
        okMsg.id = 'all-ok-msg';
        okMsg.className = 'all-ok-msg';
        pendingInfo.insertBefore(okMsg, chipsEl);
      }
      okMsg.style.display = '';
      okMsg.textContent = '\u2705 \u00a1Todas las preguntas respondidas! Ya puedes revisar tu prueba.';
    } else {
      btn.disabled = true;
      var okMsg2 = document.getElementById('all-ok-msg');
      if(okMsg2) okMsg2.style.display = 'none';
      if(pendingLabel) pendingLabel.style.display = '';
    }
  }

  // Grade calculation: min 2.0, cut=4.0 at exigency, max 7.0
  function calcNota(correctas, puntajeDirecto) {
    let puntajeAlumno;
    if (SCORING_MODE === 'manual' && typeof puntajeDirecto === 'number') {
      puntajeAlumno = puntajeDirecto;
    } else {
      puntajeAlumno = Math.round((correctas / TOTAL) * PMAX);
    }
    const corte = PMAX * EXIG;
    let nota;
    if(puntajeAlumno >= corte) {
      nota = 4 + 3 * ((puntajeAlumno - corte) / (PMAX - corte));
    } else {
      nota = 2 + 2 * (puntajeAlumno / corte);
    }
    return { nota: Math.min(7, Math.max(2, nota)), puntaje: puntajeAlumno };
  }

  document.getElementById('btn-submit').addEventListener('click', () => submitTest(false));

  let resultData = {};
  function submitTest(timeExpired) {
    if(submitted) return;
    let nombre = document.getElementById('student-name').value.trim();
    if(!nombre) {
      if (!timeExpired) {
        alert('Debes colocar tu nombre para poder revisar la prueba.');
        document.getElementById('student-name').focus();
        return;
      }
      nombre = 'Sin nombre';
    }
    submitted = true;
    clearInterval(interval);
    saveState(); // Will clear sessionStorage because submitted=true
    document.getElementById('btn-submit').disabled = true;
    document.getElementById('btn-submit').textContent = '\u2705 Revisado';

    let correct = 0;
    let puntajeManual = 0;
    const detalles = [];
    ANSWERS.forEach((ans, i) => {
      const userAns   = answered[i] || '';
      const card      = document.getElementById('qcard-'+i);
      const corrLabel = document.getElementById('q'+i+'-'+ans);
      const userLabel = answered[i] ? document.getElementById('q'+i+'-'+userAns) : null;
      const isOk      = userAns === ans;
      if(isOk) {
        correct++;
        if (SCORING_MODE === 'manual') puntajeManual += (QS[i].pts || 1);
        card.classList.add('correct');
        if(corrLabel) corrLabel.classList.add('correct-ans');
      } else {
        card.classList.add('wrong');
        if(userLabel) userLabel.classList.add('wrong-ans');
        if(SHOW_ANS && corrLabel) corrLabel.classList.add('correct-ans');
      }
      const altTexts = { A: QS[i].a, B: QS[i].b, C: QS[i].c, D: QS[i].d };
      const textCorrecta = altTexts[ans] || '';
      const textAlumno   = userAns ? (altTexts[userAns] || '') : '';
      detalles.push({ num:i+1, pregunta:QS[i].p, correcta:ans, textCorrecta, alumno:userAns, textAlumno, ok:isOk, habilidad:QS[i].h, pts: QS[i].pts || 0 });
    });

    const {nota, puntaje} = calcNota(correct, SCORING_MODE === 'manual' ? puntajeManual : undefined);
    const notaStr = nota.toFixed(1);
    const pct = Math.round(correct/TOTAL*100);
    const pass = nota >= 4.0;

    const circle = document.getElementById('score-circle');
    if(!pass) circle.classList.add('fail');
    else circle.classList.remove('fail');
    document.getElementById('score-num').textContent = correct + '/' + TOTAL;
    
    let msg = nota >= 6 ? '\u{1F389} \u00a1Excelente resultado!' : nota >= 5 ? '\u{1F44D} \u00a1Muy buen trabajo!' : nota >= 4 ? '\u{1F4AA} \u00a1Aprobado!' : '\u{1F4D6} Sigue practicando';
    document.getElementById('results-msg').textContent = msg;
    document.getElementById('results-sub').textContent = 'Obtuviste ' + correct + ' de ' + TOTAL + ' correctas \u2022 ' + puntaje + ' de ' + PMAX + ' puntos (' + pct + '%)';
    
    const badge = document.getElementById('nota-badge');
    badge.textContent = 'Nota: ' + notaStr;
    badge.className = 'nota-badge ' + (nota >= 5.0 ? 'nota-verde' : nota >= 4.0 ? 'nota-amarilla' : 'nota-roja');

    const skillsMap = {};
    let hasAnySkill = false;
    detalles.forEach(det => { if (det.habilidad && det.habilidad.trim() !== '') hasAnySkill = true; });

    let analyticsHTML = '';
    let analyticsPDFHTML = '';
    if (hasAnySkill) {
        detalles.forEach(det => {
            const h = det.habilidad && det.habilidad.trim() !== '' ? det.habilidad.trim() : 'Conceptos Generales';
            if (!skillsMap[h]) skillsMap[h] = { total: 0, correctas: 0, ptsObtenidos: 0, ptsMaximos: 0 };
            skillsMap[h].total++;
            if (det.ok) {
                skillsMap[h].correctas++;
                skillsMap[h].ptsObtenidos += (det.pts || 1);
            }
            skillsMap[h].ptsMaximos += (det.pts || 1);
        });

        const rowsUI = Object.keys(skillsMap).map(h => {
            const s = skillsMap[h];
            const pct = Math.round((s.ptsObtenidos / s.ptsMaximos) * 100);
            let nivelHtml = '';
            if (pct >= 75) nivelHtml = '<span style="color:#10b981;font-weight:700;">\uD83D\uDFE2 Adecuado</span>';
            else if (pct >= 50) nivelHtml = '<span style="color:#f59e0b;font-weight:700;">\uD83D\uDFE1 Elemental</span>';
            else nivelHtml = '<span style="color:#ef4444;font-weight:700;">\uD83D\uDD34 Insuficiente</span>';
            
            return \`<tr>
                <td style="padding:0.75rem; border-bottom:1px solid var(--border); font-weight:600;">\${escHtml(h)}</td>
                <td style="padding:0.75rem; border-bottom:1px solid var(--border); text-align:center;">\${s.correctas} / \${s.total}</td>
                <td style="padding:0.75rem; border-bottom:1px solid var(--border); text-align:center;">\${s.ptsObtenidos} / \${s.ptsMaximos} pts</td>
                <td style="padding:0.75rem; border-bottom:1px solid var(--border); text-align:center;font-weight:800;">\${pct}%</td>
                <td style="padding:0.75rem; border-bottom:1px solid var(--border); text-align:center;">\${nivelHtml}</td>
            </tr>\`;
        }).join('');

        analyticsHTML = \`
            <div style="margin-top:2rem;">
                <h3 style="font-size:1.1rem; color:var(--navy); margin-bottom:1rem; border-bottom:2px solid var(--border); padding-bottom:0.5rem;">\uD83D\uDCCA Resumen por Eje Temático</h3>
                <table style="width:100%; border-collapse:collapse; background:var(--surface2); border-radius:8px; overflow:hidden; font-size:0.9rem; margin-bottom:0;">
                    <thead><tr style="background:var(--navy); color:#fff;">
                        <th style="padding:0.75rem; text-align:left;">Eje Temático</th>
                        <th style="padding:0.75rem; text-align:center;">Correctas</th>
                        <th style="padding:0.75rem; text-align:center;">Puntaje</th>
                        <th style="padding:0.75rem; text-align:center;">Logro</th>
                        <th style="padding:0.75rem; text-align:center;">Nivel Sugerido</th>
                    </tr></thead>
                    <tbody>\${rowsUI}</tbody>
                </table>
            </div>\`;
            
        const rowsPDF = Object.keys(skillsMap).map(h => {
            const s = skillsMap[h];
            const pct = Math.round((s.ptsObtenidos / s.ptsMaximos) * 100);
            let nivelHtml = '';
            if (pct >= 75) nivelHtml = '<span style="color:#10b981;font-weight:700;">Adecuado</span>';
            else if (pct >= 50) nivelHtml = '<span style="color:#f59e0b;font-weight:700;">Elemental</span>';
            else nivelHtml = '<span style="color:#ef4444;font-weight:700;">Insuficiente</span>';
            
            return \`<tr style="background:#fff;">
                <td style="padding:0.75rem; border-bottom:1px solid #e2e8f0; font-weight:600;">\${escHtml(h)}</td>
                <td style="padding:0.75rem; border-bottom:1px solid #e2e8f0; text-align:center;">\${s.correctas} / \${s.total}</td>
                <td style="padding:0.75rem; border-bottom:1px solid #e2e8f0; text-align:center;">\${s.ptsObtenidos} / \${s.ptsMaximos} pts</td>
                <td style="padding:0.75rem; border-bottom:1px solid #e2e8f0; text-align:center;font-weight:800;">\${pct}%</td>
                <td style="padding:0.75rem; border-bottom:1px solid #e2e8f0; text-align:center;">\${nivelHtml}</td>
            </tr>\`;
        }).join('');

        analyticsPDFHTML = \`
            <div class="section-title">Resumen por Eje Temático</div>
            <table style="margin-bottom: 2rem;">
                <thead><tr>
                    <th>Eje Temático</th>
                    <th style="text-align:center;">Correctas</th>
                    <th style="text-align:center;">Puntaje</th>
                    <th style="text-align:center;">Logro</th>
                    <th style="text-align:center;">Nivel Sugerido</th>
                </tr></thead>
                <tbody>\${rowsPDF}</tbody>
            </table>\`;
            
        document.getElementById('analytics-container').innerHTML = analyticsHTML;
    }

    resultData = { nombre, nota: notaStr, notaNum: nota, puntaje, puntajeMax: PMAX, correct, total: TOTAL, pct, pass, detalles, curso: CURSO, docente: DOCENTE, asig: ASIG, titulo: TITULO, analyticsPDFHTML };

    document.getElementById('results-panel').style.display = 'block';
    document.getElementById('download-section').style.display = 'block';
    setTimeout(() => {
      document.getElementById('results-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
  }

  document.getElementById('btn-download-results').addEventListener('click', () => {
    const d = resultData;
    const status = document.getElementById('download-status');
    status.style.color = 'var(--accent)';
    status.innerHTML = '⏳ Generando PDF...';
    document.getElementById('btn-download-results').disabled = true;

    const notaColor = d.notaNum >= 5 ? '#10b981' : d.notaNum >= 4 ? '#f59e0b' : '#ef4444';
    const corr  = d.detalles.filter(x=>x.ok).length;
    const incorr = d.detalles.filter(x=>!x.ok).length;

    const ptsColHeader = SCORING_MODE === 'manual' ? '<th style="text-align:center">Pts</th>' : '';
    const rows = d.detalles.map((det,i) => {
      const ptsCol = SCORING_MODE === 'manual' ? \`<td style="text-align:center;font-weight:600;color:\${det.ok?'#10b981':'#94a3b8'};">\${det.ok ? det.pts : 0}/\${det.pts}</td>\` : '';
      return \`
      <tr style="background:\${det.ok?'rgba(16,185,129,0.07)':'rgba(239,68,68,0.07)'};">
        <td style="text-align:center;color:#94a3b8;font-weight:700;">\${det.num}</td>
        <td style="max-width:350px;">\${escHtml(det.pregunta)}</td>
        <td style="text-align:center;">\${escHtml(det.habilidad||'—')}</td>
        <td style="text-align:center;font-weight:800;color:\${det.ok?'#10b981':'#ef4444'};">\${escHtml(det.textAlumno||'—')}</td>
        <td style="text-align:center;font-weight:700;color:#10b981;">\${escHtml(det.textCorrecta)}</td>
        \${ptsCol}
        <td style="text-align:center;font-size:1.1rem;">\${det.ok?'\\u2705':'\\u274c'}</td>
      </tr>\`;
    }).join('');

    const informe = \`<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"/><title>Resultados \${escHtml(d.nombre)}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1e293b;padding:2rem;}
  .page{max-width:900px;margin:0 auto;background:#fff;padding:2rem;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,0.05);}
  h1{font-size:1.6rem;font-weight:900;color:#1a4a7a;margin-bottom:.25rem;}
  .sub{font-size:.85rem;color:#64748b;margin-bottom:2rem;}
  .kpis{display:flex;gap:1rem;margin-bottom:2rem;}
  .kpi{flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;text-align:center;}
  .kpi .lbl{font-size:.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;}
  .kpi .val{font-size:1.6rem;font-weight:900;line-height:1;}
  .nota-val{color:\${notaColor};}
  .section-title{font-size:.9rem;font-weight:700;color:#1a4a7a;text-transform:uppercase;letter-spacing:.07em;margin:2rem 0 1rem;border-left:4px solid #1a4a7a;padding-left:.75rem;}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;}
  thead th{background:#1a4a7a;color:#e8f0fe;font-size:.78rem;font-weight:700;padding:.75rem 1rem;text-align:left;text-transform:uppercase;letter-spacing:.04em;}
  tbody td{padding:.65rem 1rem;font-size:.85rem;border-bottom:1px solid #f1f5f9;}
  .stat-bar{height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden;margin-top:.3rem;}
  .stat-fill-c{height:100%;background:#10b981;border-radius:5px;}
  .stat-fill-i{height:100%;background:#ef4444;border-radius:5px;}
  @media print{ 
    body{padding:0;background:#fff;} 
    .page{box-shadow:none;padding:0;max-width:100%;}
    .no-print{display:none !important;} 
  }
</style></head><body>
<div class="page">
  <div style="display:flex; align-items:center; gap:1.5rem; margin-bottom:1.5rem;">
    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%231a4a7a'><path d='M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z'/></svg>" style="width:80px;height:auto;" alt="Logo">
    <div style="flex:1;">
      <h1>Nombre Alumno: \${escHtml(d.nombre)}</h1>
      <div class="sub">\${escHtml(d.titulo)} &bull; \${escHtml(d.asig)} &bull; Docente: \${escHtml(d.docente||'—')}</div>
    </div>
    <div class="no-print">
      <button id="btn-print-pdf" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; border: none; padding: 0.8rem 1.5rem; border-radius: 10px; font-size: 0.9rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(59,130,246,0.2); transition: transform 0.2s;">
        🖨️ Guardar PDF / Imprimir
      </button>
    </div>
  </div>
  <div class="kpis">
    <div class="kpi"><div class="lbl">Curso</div><div class="val" style="font-size:1rem;color:#475569;">\${escHtml(d.curso||'—')}</div></div>
    <div class="kpi"><div class="lbl">Nota</div><div class="val nota-val">\${d.nota}</div></div>
    <div class="kpi"><div class="lbl">Puntaje</div><div class="val" style="color:#3b82f6;">\${d.puntaje}/\${d.puntajeMax}</div></div>
    <div class="kpi"><div class="lbl">Correctas</div><div class="val" style="color:#10b981;">\${corr}</div></div>
    <div class="kpi"><div class="lbl">Incorrectas</div><div class="val" style="color:#ef4444;">\${incorr}</div></div>
  </div>
  <div class="section-title">Estadística de Preguntas</div>
  <div style="display:flex;gap:1.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <div style="flex:1;min-width:200px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;">
      <div style="font-size:.75rem;font-weight:700;color:#10b981;text-transform:uppercase;margin-bottom:.5rem;">Correctas (\${corr})</div>
      <div class="stat-bar"><div class="stat-fill-c" style="width:\${Math.round(corr/d.total*100)}%"></div></div>
      <div style="font-size:1.4rem;font-weight:900;color:#10b981;margin-top:.5rem;">\${Math.round(corr/d.total*100)}%</div>
    </div>
    <div style="flex:1;min-width:200px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;">
      <div style="font-size:.75rem;font-weight:700;color:#ef4444;text-transform:uppercase;margin-bottom:.5rem;">Incorrectas (\${incorr})</div>
      <div class="stat-bar"><div class="stat-fill-i" style="width:\${Math.round(incorr/d.total*100)}%"></div></div>
      <div style="font-size:1.4rem;font-weight:900;color:#ef4444;margin-top:.5rem;">\${Math.round(incorr/d.total*100)}%</div>
    </div>
  </div>
  \${d.analyticsPDFHTML || ''}
  <div class="section-title">Detalle por Pregunta</div>
  <table>
    <thead><tr><th style="text-align:center">#</th><th>Pregunta</th><th style="text-align:center">Habilidad</th><th>Respuesta del Alumno</th><th>Respuesta Correcta</th>\${ptsColHeader}<th style="text-align:center">Estado</th></tr></thead>
    <tbody>\${rows}</tbody>
  </table>
  <p style="margin-top:30px; font-size:.75rem; color:#94a3b8; text-align:center; border-top:1px solid #f1f5f9; padding-top:20px;">
    Este reporte fue generado automáticamente para fines pedagógicos.
  </p>
</div>
</body></html>\`;

    const w = window.open('', '_blank');
    if (w) {
      w.document.write(informe);
      w.document.close();
      
      const printBtn = w.document.getElementById('btn-print-pdf');
      if (printBtn) {
        printBtn.addEventListener('click', () => { w.print(); });
      }

      status.style.color = 'var(--success)';
      status.innerHTML = '✅ Vista previa generada. Usa el botón en la nueva ventana para descargar el PDF.';
    } else {
      status.style.color = 'var(--danger)';
      status.innerHTML = '❌ Por favor, permite las ventanas emergentes (pop-ups) para generar el PDF.';
    }
    document.getElementById('btn-download-results').disabled = false;
  });

  document.getElementById('btn-informe').addEventListener('click', () => {
    const d = resultData;
    const notaColor = d.notaNum>=5?'#10b981':d.notaNum>=4?'#f59e0b':'#ef4444';
    const ptsColHeader = SCORING_MODE === 'manual' ? '<th style="text-align:center">Pts</th>' : '';
    const rows = d.detalles.map((det,i) => {
      const ptsCol = SCORING_MODE === 'manual' ? \`<td style="text-align:center;font-weight:600;color:\${det.ok?'#10b981':'#94a3b8'};">\${det.ok ? det.pts : 0}/\${det.pts}</td>\` : '';
      return \`
      <tr style="background:\${det.ok?'rgba(16,185,129,0.07)':'rgba(239,68,68,0.07)'};">
        <td style="text-align:center;color:#94a3b8;font-weight:700;">\${det.num}</td>
        <td style="max-width:350px;">\${escHtml(det.pregunta)}</td>
        <td style="text-align:center;">\${escHtml(det.habilidad||'—')}</td>
        <td style="text-align:center;font-weight:800;color:\${det.ok?'#10b981':'#ef4444'};">\${escHtml(det.textAlumno||'—')}</td>
        <td style="text-align:center;font-weight:700;color:#10b981;">\${escHtml(det.textCorrecta)}</td>
        \${ptsCol}
        <td style="text-align:center;font-size:1.1rem;">\${det.ok?'\\u2705':'\\u274c'}</td>
      </tr>\`;
    }).join('');

    const corr  = d.detalles.filter(x=>x.ok).length;
    const incorr = d.detalles.filter(x=>!x.ok).length;
    const informe = \`<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"/><title>Retroalimentación Alumno</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1e293b;padding:2rem;}
  .page{max-width:900px;margin:0 auto;}
  h1{font-size:1.6rem;font-weight:900;color:#1a4a7a;margin-bottom:.25rem;}
  .sub{font-size:.85rem;color:#64748b;margin-bottom:2rem;}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:2rem;}
  .kpi{background:#fff;border-radius:14px;padding:1.25rem;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.08);}
  .kpi .lbl{font-size:.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;}
  .kpi .val{font-size:1.6rem;font-weight:900;line-height:1;}
  .nota-val{color:\${notaColor};}
  .section-title{font-size:.9rem;font-weight:700;color:#1a4a7a;text-transform:uppercase;letter-spacing:.07em;margin:2rem 0 1rem;border-left:4px solid #1a4a7a;padding-left:.75rem;}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.06);}
  thead th{background:#1a4a7a;color:#e8f0fe;font-size:.78rem;font-weight:700;padding:.75rem 1rem;text-align:left;text-transform:uppercase;letter-spacing:.04em;}
  tbody td{padding:.65rem 1rem;font-size:.85rem;border-bottom:1px solid #f1f5f9;}
  .stat-bar{height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden;margin-top:.3rem;}
  .stat-fill-c{height:100%;background:#10b981;border-radius:5px;}
  .stat-fill-i{height:100%;background:#ef4444;border-radius:5px;}
  .btn-retry-report {
    background: linear-gradient(135deg,#10b981,#059669); color: #fff; border: none; padding: 0.8rem 1.5rem; border-radius: 10px;
    font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items:center; gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(16,185,129,0.2);
  }
  .btn-retry-report:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16,185,129,0.3); }
  @media print{body{background:#fff;padding:0;} .no-print{display:none;}}
</style></head><body>
<div class="page">
  <div style="display:flex; align-items:center; gap:1.5rem; margin-bottom:1.5rem;">
    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%231a4a7a'><path d='M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z'/></svg>" style="width:80px;height:auto;" alt="Logo">
    <div style="flex:1;">
      <h1>Nombre Alumno: \${escHtml(d.nombre)}</h1>
      <div class="sub">\${escHtml(d.titulo)} &bull; \${escHtml(d.asig)} &bull; Docente: \${escHtml(d.docente||'—')}</div>
    </div>
    <div class="no-print">
      <button class="btn-retry-report" onclick="if(window.opener){window.opener.document.getElementById('btn-reintentar').click(); window.close();}else{alert('La ventana principal fue cerrada.');}">\uD83D\uDD04 Realizar nuevamente</button>
    </div>
  </div>
  <div class="kpis">
    <div class="kpi"><div class="lbl">Curso</div><div class="val" style="font-size:1rem;color:#475569;">\${escHtml(d.curso||'—')}</div></div>
    <div class="kpi"><div class="lbl">Nota</div><div class="val nota-val">\${d.nota}</div></div>
    <div class="kpi"><div class="lbl">Puntaje</div><div class="val" style="color:#3b82f6;">\${d.puntaje}/\${d.puntajeMax}</div></div>
    <div class="kpi"><div class="lbl">Correctas</div><div class="val" style="color:#10b981;">\${corr}</div></div>
    <div class="kpi"><div class="lbl">Incorrectas</div><div class="val" style="color:#ef4444;">\${incorr}</div></div>
  </div>
  <div class="section-title">Estadística de Preguntas</div>
  <div style="display:flex;gap:1.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <div style="flex:1;min-width:200px;background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 2px 10px rgba(0,0,0,0.06);">
      <div style="font-size:.75rem;font-weight:700;color:#10b981;text-transform:uppercase;margin-bottom:.5rem;">Correctas (\${corr})</div>
      <div class="stat-bar"><div class="stat-fill-c" style="width:\${Math.round(corr/d.total*100)}%"></div></div>
      <div style="font-size:1.4rem;font-weight:900;color:#10b981;margin-top:.5rem;">\${Math.round(corr/d.total*100)}%</div>
    </div>
    <div style="flex:1;min-width:200px;background:#fff;border-radius:14px;padding:1.25rem;box-shadow:0 2px 10px rgba(0,0,0,0.06);">
      <div style="font-size:.75rem;font-weight:700;color:#ef4444;text-transform:uppercase;margin-bottom:.5rem;">Incorrectas (\${incorr})</div>
      <div class="stat-bar"><div class="stat-fill-i" style="width:\${Math.round(incorr/d.total*100)}%"></div></div>
      <div style="font-size:1.4rem;font-weight:900;color:#ef4444;margin-top:.5rem;">\${Math.round(incorr/d.total*100)}%</div>
    </div>
  </div>
  <div class="section-title">Detalle por Pregunta</div>
  <table>
    <thead><tr><th style="text-align:center">#</th><th>Pregunta</th><th style="text-align:center">Habilidad</th><th>Respuesta del Alumno</th><th>Respuesta Correcta</th>\${ptsColHeader}<th style="text-align:center">Estado</th></tr></thead>
    <tbody>\${rows}</tbody>
  </table>
  <p style="margin-top:30px; font-size:.75rem; color:#94a3b8; text-align:center; border-top:1px solid #f1f5f9; padding-top:20px;" class="no-print">
    Este reporte fue generado automáticamente para fines pedagógicos.
  </p>
</div></body></html>\`;

    const w = window.open('','_blank');
    w.document.write(informe);
    w.document.close();
    feedbackWindow = w;
  });

  document.getElementById('btn-reintentar').addEventListener('click', () => {
      // Cierra la ventana de retroalimentación si está abierta.
      if (typeof feedbackWindow !== 'undefined' && feedbackWindow && !feedbackWindow.closed) {
        feedbackWindow.close();
      }
      // Limpia el estado guardado de la sesión actual.
      try { sessionStorage.removeItem(stateKey); } catch(e) {}
      
      // Reiniciar variables
      answered = {};
      submitted = false;
      timerSecs = TIEMPO * 60;
      
      // Ocultar panel de resultados
      document.getElementById('results-panel').style.display = 'none';
      const btnSubmit = document.getElementById('btn-submit');
      btnSubmit.textContent = '\uD83D\uDD0D Revisar Prueba';
      btnSubmit.disabled = true;
      
      // Limpiar todas las selecciones y colores
      document.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
      document.querySelectorAll('.alt-label').forEach(l => l.classList.remove('selected', 'correct-ans', 'wrong-ans'));
      document.querySelectorAll('.question-card').forEach(c => c.classList.remove('answered', 'correct', 'wrong'));
      
      // Reiniciar barra de progreso y temporizador
      updateProgress();
      if(interval) clearInterval(interval);
      startTimer();
      window.scrollTo({top: 0, behavior: 'smooth'});
  });

<\/script>
${katexBodyHtml}
</body></html>`;
            testGeneratedAtLeastOnce = true;
            document.getElementById('section-generate').style.display = 'block';
            const regenWarn = document.getElementById('regen-warning');
            if (regenWarn) regenWarn.style.display = 'none';
            document.getElementById('section-generate').scrollIntoView({ behavior: 'smooth', block: 'start' });
            setStep(4);
            toast('🚀 Prueba generada exitosamente');

            // Cargar lista de cursos/asignaturas para "Guardar en el sistema"
            const saveResultBox = document.getElementById('save-system-result');
            if (saveResultBox) saveResultBox.style.display = 'none';
            loadSaveSystemFolders();
        });

        /* ── OPEN / DOWNLOAD TEST ── */
        document.getElementById('btn-open-test').addEventListener('click', () => {
            const blob = new Blob([generatedHTML], { type: 'text/html;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
        });

        document.getElementById('btn-download-test').addEventListener('click', () => {
            const rawName = document.getElementById('cfg-filename').value.trim();
            const safeName = rawName
                ? rawName.replace(/[^a-zA-Z0-9_\-áéíóúÁÉÍÓÚñÑ]/g, '_').replace(/_{2,}/g, '_') + '.html'
                : 'reforzamiento_simce_listo.html';
            const blob = new Blob(['\uFEFF' + generatedHTML], { type: 'text/html;charset=utf-8' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = safeName;
            a.click();
            clearDraft();
            toast('⬇️ Archivo descargado como: ' + safeName);
        });

        /* ── IMPRIMIR PRUEBA (PDF) ── */
        function triggerPrint(isTeacher) {
            const titulo = document.getElementById('cfg-titulo').value || 'Prueba';
            const selAsig = document.getElementById('cfg-asignatura');
            const asignatura = selAsig.value === 'Otra'
                ? (document.getElementById('cfg-asignatura-otra').value.trim() || 'Otra')
                : selAsig.value;
            const curso = document.getElementById('cfg-curso').value || '';
            const docente = document.getElementById('cfg-docente').value || '';
            const tiempo = document.getElementById('cfg-tiempo').value || 45;
            const instrucciones = document.getElementById('cfg-instrucciones').value || 'Lee atentamente cada pregunta y marca la alternativa correcta.';

            let lastPassage = '';
            const printQuestionsHTML = questions.map((q, i) => {
                const alts = ['A', 'B', 'C', 'D'].map(l => {
                    const val = q[`Alternativa_${l}`];
                    if (!val) return '';
                    const isCorrect = isTeacher && q['Respuesta_Correcta'] === l;
                    const highlightStyle = isCorrect ? 'background-color:#dcfce7; border: 1px solid #22c55e; font-weight:bold;' : '';
                    return `<li style="display:flex; align-items:flex-start; margin-bottom:10px; padding: 4px 8px; border-radius: 4px; ${highlightStyle}">
                              <span style="display:inline-block; width:18px; height:18px; border:1.5px solid #333; border-radius:50%; margin-right:10px; margin-top:2px; flex-shrink:0;"></span>
                              <span style="font-weight:bold; margin-right:6px;">${l})</span>
                              <span style="flex:1;">${escHtml(val)}</span>
                            </li>`;
                }).join('');

                const safeImg = safeImageSrc(q['_imagen']);
                const imgHtml = safeImg ? `<img src="${escHtml(safeImg)}" class="q-img" alt="Imagen de la pregunta"/>` : '';
                
                let passageHtml = '';
                if (q['Texto_Lectura'] && q['Texto_Lectura'] !== lastPassage) {
                    passageHtml = `<div style="background: #f8fafc; padding: 15px; border-left: 4px solid #64748b; margin-bottom: 15px; font-size: 10.5pt; white-space: pre-wrap;"><strong>Lectura:</strong><br/>${escHtml(q['Texto_Lectura'])}</div>`;
                    lastPassage = q['Texto_Lectura'];
                } else if (!q['Texto_Lectura']) {
                    lastPassage = '';
                }

                return `
                ${passageHtml}
                <div class="question">
                    <div class="q-text">${i + 1}. ${escHtml(q['Pregunta'])}</div>
                    ${imgHtml}
                    <ul class="alts">${alts}</ul>
                </div>`;
            }).join('');

            const pautaHTML = isTeacher ? questions.map((q, i) => {
                return `<tr><td style="border: 1px solid #ccc; padding: 8px; text-align: center;">${i + 1}</td><td style="border: 1px solid #ccc; padding: 8px; text-align: center; font-weight: bold;">${escHtml((q['Respuesta_Correcta'] || '').toUpperCase())}</td><td style="border: 1px solid #ccc; padding: 8px;">${escHtml(q['Habilidad'] || '-')}</td></tr>`;
            }).join('') : '';

            const pautaSection = isTeacher ? `
  <div style="page-break-before: always; margin-top: 30px;">
    <h2>Pauta de Corrección (Uso del Docente)</h2>
    <p>Esta página contiene las respuestas correctas. <strong>No la entregues a los alumnos.</strong></p>
    <table class="pauta-table">
      <thead>
        <tr><th>Pregunta</th><th>Respuesta Correcta</th><th>Habilidad</th></tr>
      </thead>
      <tbody>
        ${pautaHTML}
      </tbody>
    </table>
  </div>` : '';

            const printHTML = `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <title>${escHtml(titulo)} - ${isTeacher ? 'Pauta Docente' : 'Prueba Alumno'}</title>
  <style>
    body { font-family: 'Arial', sans-serif; font-size: 11pt; color: #000; background: #fff; margin: 0; padding: 2cm; line-height: 1.4; }
    h1 { font-size: 16pt; text-align: center; margin-bottom: 5px; text-transform: uppercase; }
    h2 { font-size: 14pt; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 5px; }
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; border: 1px solid #000; padding: 15px; border-radius: 8px; }
    .student-info { margin-bottom: 20px; border: 1px solid #000; padding: 15px; display: grid; grid-template-columns: 2fr 1fr; gap: 10px; border-radius: 8px; }
    .instructions { font-style: italic; margin-bottom: 30px; border-bottom: 1px dashed #ccc; padding-bottom: 15px; }
    .question { margin-bottom: 25px; page-break-inside: avoid; }
    .q-text { font-weight: bold; margin-bottom: 10px; }
    .q-img { max-width: 400px; max-height: 300px; display: block; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; }
    .alts { list-style: none; padding-left: 0; margin: 0; }
    .alts li { margin-bottom: 8px; display: flex; }
    .alts li span.letter { display: inline-block; width: 25px; font-weight: bold; flex-shrink: 0; }
    .pauta-table { width: 100%; max-width: 600px; border-collapse: collapse; margin-top: 20px; }
    .pauta-table th { background: #f0f0f0; border: 1px solid #ccc; padding: 10px; text-align: center; }
    @media print {
      body { padding: 0; }
      @page { margin: 1.5cm; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <h1>${escHtml(titulo)}</h1>
  ${isTeacher ? '<h3 style="text-align:center; color:#d97706; margin-top:-5px;">PAUTA DE CORRECCIÓN</h3>' : ''}
  <div class="meta">
    <div><strong>Asignatura:</strong> ${escHtml(asignatura)}</div>
    <div><strong>Docente:</strong> ${escHtml(docente) || '__________________'}</div>
    <div><strong>Curso:</strong> ${escHtml(curso) || '__________________'}</div>
    <div><strong>Tiempo Sugerido:</strong> ${escHtml(String(tiempo))} min</div>
  </div>
  ${!isTeacher ? `
  <div class="student-info">
    <div><strong>Nombre del Alumno:</strong> _____________________________________________________</div>
    <div><strong>Fecha:</strong> ___/___/20___</div>
  </div>` : ''}
  <div class="instructions">
    <strong>Instrucciones:</strong> ${escHtml(instrucciones)}
  </div>

  <div class="questions">
    ${printQuestionsHTML}
  </div>

  ${pautaSection}

  <div class="no-print" style="position: fixed; top: 20px; right: 20px; background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <button onclick="window.print()" style="background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 14px;">🖨️ Imprimir ahora</button>
  </div>
  <script>
    window.onload = function() { setTimeout(function(){ window.print(); }, 500); };
  </script>
</body>
</html>`;
            let iframe = document.getElementById('print-iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'print-iframe';
                iframe.style.position = 'absolute';
                iframe.style.width = '0px';
                iframe.style.height = '0px';
                iframe.style.border = 'none';
                document.body.appendChild(iframe);
            }
            
            iframe.contentWindow.document.open();
            iframe.contentWindow.document.write(printHTML);
            iframe.contentWindow.document.close();
            
            toast('🖨️ Preparando impresión...');
        }

        document.getElementById('btn-print-student').addEventListener('click', () => triggerPrint(false));
        document.getElementById('btn-print-teacher').addEventListener('click', () => triggerPrint(true));

        /* ── GUARDAR PRUEBA EN EL SISTEMA ── */
        var SAVE_SUBJECT_LABELS = {
            'lenguaje': 'Lenguaje',
            'matematica': 'Matemáticas',
            'ciencia': 'Ciencias',
            'historia': 'Historia',
            'ingles': 'Inglés'
        };
        var saveFoldersStructure = {};

        function formatCourseLabel(folder) {
            var m = folder.match(/^(\d+)(basico|medio)$/i);
            if (m) {
                var nivel = m[2].toLowerCase() === 'basico' ? 'Básico' : 'Medio';
                return m[1] + '° ' + nivel;
            }
            return folder;
        }

        function formatSubjectLabel(folder) {
            return SAVE_SUBJECT_LABELS[folder.toLowerCase()] || (folder.charAt(0).toUpperCase() + folder.slice(1));
        }

        function loadSaveSystemFolders() {
            var cursoSel = document.getElementById('save-curso');
            var asigSel = document.getElementById('save-asignatura');
            if (!cursoSel || !asigSel) return;
            fetch('guardar_prueba.php')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    saveFoldersStructure = data || {};
                    var courses = Object.keys(saveFoldersStructure).sort();
                    if (courses.length === 0) {
                        cursoSel.innerHTML = '<option value="">No hay carpetas de cursos disponibles</option>';
                        asigSel.innerHTML = '<option value="">—</option>';
                        return;
                    }
                    cursoSel.innerHTML = '<option value="">Selecciona un curso…</option>' +
                        courses.map(function(c) { return '<option value="' + escHtml(c) + '">' + escHtml(formatCourseLabel(c)) + '</option>'; }).join('');
                    asigSel.innerHTML = '<option value="">Selecciona un curso primero</option>';
                })
                .catch(function() {
                    cursoSel.innerHTML = '<option value="">⚠ No se pudo cargar la lista de cursos</option>';
                    asigSel.innerHTML = '<option value="">—</option>';
                });
        }

        function onSaveCursoChange() {
            var curso = document.getElementById('save-curso').value;
            var asigSel = document.getElementById('save-asignatura');
            var subjects = saveFoldersStructure[curso] || [];
            if (!curso || subjects.length === 0) {
                asigSel.innerHTML = '<option value="">Selecciona un curso primero</option>';
                return;
            }
            asigSel.innerHTML = '<option value="">Selecciona una asignatura…</option>' +
                subjects.map(function(s) { return '<option value="' + escHtml(s) + '">' + escHtml(formatSubjectLabel(s)) + '</option>'; }).join('');
        }

        function showSaveSystemResult(ok, msg) {
            var box = document.getElementById('save-system-result');
            box.style.display = 'block';
            box.className = 'save-system-result ' + (ok ? 'ok' : 'error');
            box.textContent = msg;
        }

        document.getElementById('btn-save-system').addEventListener('click', function() {
            var curso = document.getElementById('save-curso').value;
            var asignatura = document.getElementById('save-asignatura').value;
            if (!curso || !asignatura) {
                showSaveSystemResult(false, '⚠️ Selecciona un curso y una asignatura antes de guardar.');
                return;
            }
            if (!generatedHTML) {
                showSaveSystemResult(false, '⚠️ Primero debes generar la prueba.');
                return;
            }
            var rawName = document.getElementById('cfg-filename').value.trim();
            var btn = this;
            btn.disabled = true;
            btn.textContent = '⏳ Guardando…';
            window.simceAuthenticatedFetch('guardar_prueba.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ curso: curso, asignatura: asignatura, filename: rawName, titulo: document.getElementById('cfg-titulo').value, html: generatedHTML })
            })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.ok) {
                        showSaveSystemResult(true, '✅ Prueba guardada en el sistema: ' + res.path + '. Ya está disponible en el menú de pruebas.');
                        toast('💾 Prueba guardada en ' + formatCourseLabel(curso) + ' / ' + formatSubjectLabel(asignatura));
                    } else {
                        showSaveSystemResult(false, '⚠️ ' + ((res && res.error) || 'No se pudo guardar la prueba.'));
                    }
                })
                .catch(function() {
                    showSaveSystemResult(false, '⚠️ Error de conexión al guardar la prueba en el servidor.');
                })
                .finally(function() {
                    btn.disabled = false;
                    btn.textContent = '💾 Guardar en el sistema';
                });
        });

        document.getElementById('save-curso').addEventListener('change', onSaveCursoChange);

        /* ── NEW ── */
        document.getElementById('btn-new').addEventListener('click', () => {
            testGeneratedAtLeastOnce = false;
            questions = [];
            generatedHTML = '';
            // Reset Excel mode
            document.getElementById('excel-input').value = '';
            document.getElementById('file-name-label').style.display = 'none';
            // Reset form builder mode
            fbQuestions = [];
            renderFormBuilder();
            switchMode('excel');
            // Hide steps
            document.getElementById('step-config').style.display = 'none';
            document.getElementById('step-preview').style.display = 'none';
            document.getElementById('section-generate').style.display = 'none';
            // Reset save-to-system box
            const saveResultBox = document.getElementById('save-system-result');
            if (saveResultBox) saveResultBox.style.display = 'none';
            document.getElementById('save-curso').innerHTML = '<option value="">Cargando cursos…</option>';
            document.getElementById('save-asignatura').innerHTML = '<option value="">Selecciona un curso primero</option>';
            // Reset config fields
            ['cfg-titulo', 'cfg-curso', 'cfg-docente', 'cfg-instrucciones'].forEach(id => {
                document.getElementById(id).value = '';
            });
            document.getElementById('cfg-tiempo').value = 45;
            document.getElementById('cfg-modo-puntaje').value = 'proporcional';
            toggleScoringMode();
            setStep(1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        /* ── DOWNLOAD TEMPLATE ── */
        function downloadTemplate() {
            const ws_data = [
                ['Pregunta', 'Alternativa_A', 'Alternativa_B', 'Alternativa_C', 'Alternativa_D', 'Respuesta_Correcta', 'Habilidad', 'Texto_Lectura', 'Puntaje'],
                ['¿Cuál es la capital de Chile?', 'Santiago', 'Valparaíso', 'Concepción', 'Temuco', 'A', 'Geografía', '', '2'],
                ['Según el texto anterior, ¿qué sucedió al inicio?', 'Nada', 'Mucho', 'Poco', 'Todo', 'B', 'Comprensión', 'Había una vez un texto de prueba para lectura...', '3'],
            ];
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(ws_data);
            ws['!cols'] = [{ wch: 50 }, { wch: 25 }, { wch: 25 }, { wch: 25 }, { wch: 25 }, { wch: 20 }, { wch: 25 }, { wch: 40 }, { wch: 10 }];
            XLSX.utils.book_append_sheet(wb, ws, 'Preguntas');
            XLSX.writeFile(wb, 'planilla prueba.xlsx');
            toast('⬇️ Planilla descargada');
        }

        // Attach listeners globally once
        window.addEventListener('load', () => {

            // Check for drafts
            try {
                const draft = localStorage.getItem('pruebas_draft');
                if (draft && JSON.parse(draft).length > 0) {
                    document.getElementById('draft-alert').style.display = 'flex';
                }
            } catch (e) {
                console.warn('Draft could not be parsed:', e);
                localStorage.removeItem('pruebas_draft');
            }

            const configIds = ['cfg-titulo', 'cfg-asignatura', 'cfg-asignatura-otra', 'cfg-curso', 'cfg-docente', 'cfg-tiempo', 'cfg-mostrar-resp', 'cfg-shuffle-alts', 'cfg-filename', 'cfg-puntaje', 'cfg-dificultad', 'cfg-instrucciones', 'cfg-modo-puntaje'];
            configIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', hideGeneratedStep);
                    el.addEventListener('change', hideGeneratedStep);
                }
            });

            // Construir teclado matemático visual
            buildMathKeyboard();

            // Modal: toggle LaTeX mode
            document.getElementById('btn-toggle-latex-mode').addEventListener('click', toggleLatexMode);

            // Modal: input LaTeX → actualizar preview en tiempo real
            document.getElementById('formula-latex-input').addEventListener('input', updateFormulaPreview);
            document.getElementById('formula-display-mode').addEventListener('change', updateFormulaPreview);

            // Constructor de fracción: preview en tiempo real y teclas de navegación
            document.getElementById('mk-frac-num').addEventListener('input', updateFracPreview);
            document.getElementById('mk-frac-den').addEventListener('input', updateFracPreview);
            document.getElementById('mk-frac-num').addEventListener('keydown', function(e) {
                if (e.key === 'Tab' || e.key === 'Enter') { e.preventDefault(); document.getElementById('mk-frac-den').focus(); }
            });
            document.getElementById('mk-frac-den').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); insertFrac(); }
            });

            // Modal: botón insertar
            document.getElementById('btn-insert-formula').addEventListener('click', insertFormulaIntoInput);

            // Modal: cerrar al hacer clic en el fondo
            document.getElementById('formula-modal').addEventListener('click', function(e) {
                if (e.target === this) closeFormulaModal();
            });

            // Modal: cerrar con Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('formula-modal').style.display !== 'none') {
                    closeFormulaModal();
                }
            });

            // Seguimiento de foco dentro del formulario (para saber dónde insertar fórmulas)
            document.getElementById('fb-container').addEventListener('focusin', function(e) {
                if (e.target && (e.target.classList.contains('fb-q-text') || e.target.classList.contains('fb-alt-input'))) {
                    focusedFbInput = e.target;
                    document.getElementById('formula-modal')._targetInput = e.target;
                }
            });

            // Vista previa matemática en tiempo real (enunciado y alternativas)
            document.getElementById('fb-container').addEventListener('input', function(e) {
                debouncedSaveDraft();
                if (!mathModeEnabled) return;
                if (!e.target) return;
                var isQuestion = e.target.classList.contains('fb-q-text');
                var isAlt      = e.target.classList.contains('fb-alt-input');
                if (isQuestion || isAlt) {
                    var cardEl = e.target.closest('.fb-question-card');
                    if (cardEl) {
                        var idx = parseInt(cardEl.id.replace('fbq-', ''));
                        if (!isNaN(idx)) updateMathPreviewForCard(idx);
                    }
                }
            });

            // Activar modo matemáticas automáticamente al seleccionar Matemáticas
            var asigSelect = document.getElementById('cfg-asignatura');
            function checkAutoMathMode() {
                if (asigSelect.value === 'Matemáticas' || asigSelect.value === 'Matematicas') {
                    setMathMode(true);
                }
            }
            asigSelect.addEventListener('change', checkAutoMathMode);

            // Form builder: continuar
            document.getElementById('btn-fb-continue').addEventListener('click', () => {
                syncAllFbQuestions();

                const errors = [];
                fbQuestions.forEach((q, i) => {
                    if (!q.text.trim()) errors.push(`Pregunta ${i + 1}: falta el enunciado.`);
                    else if (!q.a.trim() || !q.b.trim()) errors.push(`Pregunta ${i + 1}: necesita al menos las alternativas A y B.`);
                    else if (!q.correct) errors.push(`Pregunta ${i + 1}: no marcaste la respuesta correcta.`);
                });
                if (errors.length) { toast('❌ ' + errors[0], 'error'); return; }

                questions = fbQuestions.map(q => {
                    const obj = {
                        'Pregunta': q.text,
                        'Alternativa_A': q.a,
                        'Alternativa_B': q.b,
                        'Alternativa_C': q.c,
                        'Alternativa_D': q.d,
                        'Respuesta_Correcta': q.correct,
                        'Habilidad': q.skill,
                        'Texto_Lectura': q.passage
                    };
                    if (q.img) obj['_imagen'] = q.img;
                    if (getScoringMode() === 'manual') obj['Puntaje'] = q.points || 1;
                    return obj;
                });

                document.getElementById('step-config').style.display = 'block';
                document.getElementById('step-preview').style.display = 'none';
                hideGeneratedStep();
                document.getElementById('step-config').scrollIntoView({ behavior: 'smooth', block: 'start' });
                setStep(2);
                toast(`✅ ${questions.length} pregunta(s) listas para configurar`);
            });
        });
        /* ── IMPORT HTML TEST ── */
        function importHTMLTest(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const text = e.target.result;
                let originalName = file.name;
                if (originalName.toLowerCase().endsWith('.html')) {
                    originalName = originalName.substring(0, originalName.length - 5);
                }
                processImportedHTML(text, originalName);
                document.getElementById('import-html-input').value = '';
            };
            reader.readAsText(file);
        }

        function processImportedHTML(text, originalFilename) {
            // Extract QS using regex
            const qsMatch = text.match(/const\s+QS\s*=\s*(\[[\s\S]*?\]);\s*const\s+TOTAL/);
            if (!qsMatch) {
                toast('❌ Archivo no válido o versión antigua', 'error');
                return;
            }
            
            try {
                const parsedQS = JSON.parse(qsMatch[1]);
                
                // Extraer metadatos
                const getConst = (varName) => {
                    const m = text.match(new RegExp(`const\\s+${varName}\\s*=\\s*(.*?);`));
                    if (m) {
                        try { return JSON.parse(m[1]); } 
                        catch(e) { return m[1].replace(/['"`]/g, '').trim(); }
                    }
                    return '';
                };

                const titulo = getConst('TITULO');
                const asig = getConst('ASIG');
                const curso = getConst('CURSO');
                const docente = getConst('DOCENTE');
                
                // Set fields
                if (titulo) document.getElementById('cfg-titulo').value = titulo;
                if (curso) document.getElementById('cfg-curso').value = curso;
                if (docente) document.getElementById('cfg-docente').value = docente;
                
                if (originalFilename) {
                    document.getElementById('cfg-filename').value = originalFilename;
                    document.getElementById('filename-help-box').style.display = 'block';
                    document.getElementById('cfg-filename').classList.add('input-editing-mode');
                    document.getElementById('lbl-filename').innerHTML = '✏️ Editando archivo de prueba (CUIDADO AL GUARDAR)';
                    document.getElementById('lbl-filename').classList.add('label-editing-mode');
                } else {
                    document.getElementById('cfg-filename').classList.remove('input-editing-mode');
                    document.getElementById('lbl-filename').innerHTML = 'Nombre del archivo de la prueba';
                    document.getElementById('lbl-filename').classList.remove('label-editing-mode');
                    document.getElementById('filename-help-box').style.display = 'none';
                }
                
                if (asig) {
                    const asigSelect = document.getElementById('cfg-asignatura');
                    let asigFound = false;
                    for(let i=0; i<asigSelect.options.length; i++) {
                        if(asigSelect.options[i].value === asig) { asigFound = true; break; }
                    }
                    if(asigFound) {
                        asigSelect.value = asig;
                        toggleOtra(asigSelect);
                    } else {
                        asigSelect.value = 'Otra';
                        toggleOtra(asigSelect);
                        document.getElementById('cfg-asignatura-otra').value = asig;
                    }
                }

                // Map questions back to fbQuestions format
                fbQuestions = parsedQS.map(q => ({
                    id: 'q_' + Math.random().toString(36).substr(2, 9),
                    text: q.p || '',
                    a: q.a || '',
                    b: q.b || '',
                    c: q.c || '',
                    d: q.d || '',
                    correct: q.r || '',
                    skill: q.h || '',
                    passage: q.pas || '',
                    img: q.img || '',
                    points: q.pts || 1
                }));

                saveDraft();
                switchMode('form');
                renderFormBuilder();
                toast('✅ Prueba importada con éxito');

            } catch (err) {
                console.error(err);
                toast('❌ Error al procesar el archivo HTML', 'error');
            }
        }
        /* ── IMAGE SEARCH (WIKIMEDIA COMMONS) ── */
        let currentSearchQuestionIdx = null;
        let imageSearchMode = 'form'; // 'form' or 'preview'

        function openImageSearch(idx, mode = 'form') {
            currentSearchQuestionIdx = idx;
            imageSearchMode = mode;
            document.getElementById('image-search-modal').style.display = 'flex';
            document.getElementById('img-search-input').value = '';
            document.getElementById('img-search-results').innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 2rem 0;">Escribe un término arriba y presiona Buscar.</div>';
            setTimeout(() => document.getElementById('img-search-input').focus(), 100);
        }

        function closeImageSearch() {
            document.getElementById('image-search-modal').style.display = 'none';
        }

        async function performImageSearch() {
            const query = document.getElementById('img-search-input').value.trim();
            if (!query) return;
            
            document.getElementById('img-search-results').innerHTML = '';
            document.getElementById('img-search-loading').style.display = 'block';
            
            try {
                const wikiUrl = `https://commons.wikimedia.org/w/api.php?action=query&generator=search&gsrsearch=filetype:bitmap|drawing ${encodeURIComponent(query)}&gsrnamespace=6&gsrlimit=15&prop=imageinfo&iiprop=url&iiurlwidth=400&format=json&origin=*`;
                const wikiResponse = await fetch(wikiUrl);
                if (!wikiResponse.ok) throw new Error('Wikimedia no respondió correctamente.');
                const wikiData = await wikiResponse.json();
                
                document.getElementById('img-search-loading').style.display = 'none';
                const resultsContainer = document.getElementById('img-search-results');
                
                let combinedResults = [];
                
                // Procesar Wikimedia
                if (wikiData.query && wikiData.query.pages) {
                    Object.values(wikiData.query.pages).forEach(page => {
                        if (page.imageinfo && page.imageinfo[0]) {
                            combinedResults.push({
                                thumb: page.imageinfo[0].thumburl,
                                full: page.imageinfo[0].thumburl,
                                title: page.title.replace('File:', ''),
                                source: 'Wiki',
                                color: '#2563eb' // Azul
                            });
                        }
                    });
                }
                
                if (combinedResults.length === 0) {
                    resultsContainer.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 2rem 0;">No se encontraron resultados para esta búsqueda.</div>';
                    return;
                }
                
                // Mezclar resultados aleatoriamente
                combinedResults.sort(() => Math.random() - 0.5);
                
                combinedResults.forEach(item => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'img-result-wrapper';
                    
                    const imgEl = document.createElement('img');
                    const safeThumb = safeImageSrc(item.thumb);
                    const safeFull = safeImageSrc(item.full);
                    if (!safeThumb || !safeFull) return;
                    imgEl.src = safeThumb;
                    imgEl.style.width = '100%';
                    imgEl.style.height = '140px';
                    imgEl.style.objectFit = 'cover';
                    imgEl.style.borderRadius = '8px';
                    imgEl.style.cursor = 'pointer';
                    imgEl.style.border = '2px solid transparent';
                    imgEl.style.transition = 'all 0.2s';
                    imgEl.title = item.title;
                    
                    imgEl.onmouseover = () => imgEl.style.border = `2px solid ${item.color}`;
                    imgEl.onmouseout = () => imgEl.style.border = '2px solid transparent';
                    imgEl.onclick = () => selectOnlineImage(safeFull);
                    
                    const badge = document.createElement('span');
                    badge.className = 'img-source-badge';
                    badge.textContent = item.source;
                    badge.style.backgroundColor = item.color;
                    
                    wrapper.appendChild(imgEl);
                    wrapper.appendChild(badge);
                    resultsContainer.appendChild(wrapper);
                });
                
            } catch (e) {
                document.getElementById('img-search-loading').style.display = 'none';
                const errorBox = document.createElement('div');
                errorBox.style.cssText = 'color:var(--danger);grid-column:1/-1;text-align:center;';
                errorBox.textContent = 'Error general en la búsqueda: ' + e.message;
                document.getElementById('img-search-results').replaceChildren(errorBox);
            }
        }

        function selectOnlineImage(imageUrl) {
            if (currentSearchQuestionIdx !== null) {
                if (imageSearchMode === 'form') {
                    syncFbQuestion(currentSearchQuestionIdx);
                    fbQuestions[currentSearchQuestionIdx].img = imageUrl;
                    renderFormBuilder();
                    saveDraft();
                } else if (imageSearchMode === 'preview') {
                    questions[currentSearchQuestionIdx]['_imagen'] = imageUrl;
                    renderImgCell(currentSearchQuestionIdx);
                    hideGeneratedStep();
                }
                toast('🖼️ Imagen insertada correctamente');
                closeImageSearch();
            }
        }

        /* ── BIBLIOTECA DEL SERVIDOR (API.PHP) ── */
        function openServerLibraryModal() {
            document.getElementById('server-library-modal').style.display = 'flex';
            loadServerLibrary();
        }

        function closeServerLibraryModal() {
            document.getElementById('server-library-modal').style.display = 'none';
        }

        function loadServerLibrary() {
            var loading = document.getElementById('server-library-loading');
            var error = document.getElementById('server-library-error');
            var content = document.getElementById('server-library-content');
            
            loading.style.display = 'block';
            error.style.display = 'none';
            content.style.display = 'none';
            content.innerHTML = '';
            
            fetch('api_v21.php')
                .then(function(response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(function(data) {
                    loading.style.display = 'none';
                    if (Object.keys(data).length === 0) {
                        content.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--text-muted);">Aún no hay pruebas guardadas en el servidor.</div>';
                        content.style.display = 'block';
                        return;
                    }
                    
                    // Clasificar por Nivel (Básica, Media, Otros)
                    var levels = {
                        'Basica': { icon: '🏫', title: 'Enseñanza Básica', courses: {} },
                        'Media': { icon: '🎓', title: 'Enseñanza Media', courses: {} },
                        'Otros': { icon: '📁', title: 'Otras Carpetas', courses: {} }
                    };

                    for (var course in data) {
                        var courseLower = course.toLowerCase();
                        if (courseLower.includes('basico') || courseLower.includes('basica')) {
                            levels['Basica'].courses[course] = data[course];
                        } else if (courseLower.includes('medio') || courseLower.includes('media')) {
                            levels['Media'].courses[course] = data[course];
                        } else {
                            levels['Otros'].courses[course] = data[course];
                        }
                    }

                    var html = '';
                    
                    for (var levelKey in levels) {
                        var levelObj = levels[levelKey];
                        var courseKeys = Object.keys(levelObj.courses);
                        if (courseKeys.length === 0) continue;
                        
                        html += '<details class="lib-level-details">';
                        html += '<summary class="lib-level-summary">';
                        html += '<span class="lib-accordion-icon">▶</span>';
                        html += levelObj.icon + ' ' + levelObj.title;
                        html += '</summary>';
                        html += '<div class="lib-level-content">';
                        
                        for (var i = 0; i < courseKeys.length; i++) {
                            var course = courseKeys[i];
                            var subjects = levelObj.courses[course];
                            var subjectKeys = Object.keys(subjects);
                            if (subjectKeys.length === 0) continue;

                            html += '<details class="lib-course-details">';
                            html += '<summary class="lib-course-summary">';
                            html += '<span class="lib-accordion-icon">▶</span>';
                            html += escHtml(formatCourseLabel(course));
                            html += '</summary>';
                            html += '<div class="lib-course-content">';

                            for (var j = 0; j < subjectKeys.length; j++) {
                                var subject = subjectKeys[j];
                                var tests = subjects[subject];
                                if (tests.length === 0) continue;
                                
                                html += '<details class="lib-subject-details">';
                                html += '<summary class="lib-subject-summary">';
                                html += '<span class="lib-accordion-icon">▶</span>';
                                html += '📚 ' + escHtml(formatSubjectLabel(subject)) + ' <span style="font-size:0.8rem; font-weight:normal; color:#6b7280;">(' + tests.length + ' pruebas)</span>';
                                html += '</summary>';
                                html += '<div class="lib-subject-content">';
                                
                                html += '<div class="lib-tests-grid">';
                                tests.forEach(function(test) {
                                    html += '<div class="lib-test-card">';
                                    html += '<div class="lib-test-title">' + escHtml(test.titulo) + '</div>';
                                    html += '<div class="lib-test-actions">';
                                    var safeUrl = safeServerTestUrl(test.url);
                                    if (safeUrl) {
                                        html += '<button class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem; margin:0;" onclick="loadTestFromServer(' + escHtml(JSON.stringify(safeUrl)) + ',' + escHtml(JSON.stringify(test.archivo || test.titulo)) + ')">✏️ Editar</button>';
                                    }
                                    html += '</div>';
                                    html += '</div>';
                                });
                                html += '</div>'; // end grid

                                html += '</div>'; // end lib-subject-content
                                html += '</details>'; // end lib-subject-details
                            }

                            html += '</div>'; // end lib-course-content
                            html += '</details>'; // end lib-course-details
                        }

                        html += '</div>'; // end lib-level-content
                        html += '</details>'; // end lib-level-details
                    }
                    
                    content.innerHTML = html;
                    content.style.display = 'block';
                })
                .catch(function(err) {
                    console.error('Error cargando biblioteca:', err);
                    loading.style.display = 'none';
                    error.style.display = 'block';
                });
        }

        function loadTestFromServer(url, originalFilename) {
            url = safeServerTestUrl(url);
            if (!url) {
                alert('La dirección de la prueba no es válida.');
                return;
            }
            toast('Descargando prueba del servidor... ⏳');
            var fetchUrl = url + (url.indexOf('?') !== -1 ? '&' : '?') + 't=' + Date.now();
            fetch(fetchUrl)
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ' al intentar descargar: ' + url);
                    }
                    return response.text();
                })
                .then(function(htmlContent) {
                    closeServerLibraryModal();
                    
                    // Extraer el nombre del archivo de la url (ej: pruebas/curso/asig/Prueba_Final.html -> Prueba_Final)
                    var parts = url.split('/');
                    var fileName = originalFilename || parts[parts.length - 1].split('?')[0];
                    try { fileName = decodeURIComponent(fileName); } catch(e){}
                    if (fileName.toLowerCase().endsWith('.html')) {
                        fileName = fileName.substring(0, fileName.length - 5);
                    }
                    
                    processImportedHTML(htmlContent, fileName);
                    toast('✅ Prueba cargada exitosamente del servidor');
                })
                .catch(function(err) {
                    console.error(err);
                    alert('Error de conexión o archivo no encontrado.\\n\\nDetalle: ' + err.message + '\\n\\n(Asegúrate de que la prueba exista realmente en esa carpeta de tu servidor)');
                });
        }
