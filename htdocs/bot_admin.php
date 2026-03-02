<?php
// bot_admin.php - Bot Control Center Dashboard
// This page calls your local bot_server.py via ngrok.
// Before using: run "python bot_server.py" locally, get your ngrok URL, and paste it below.
// OR: leave empty and enter it in the dashboard's settings box.
session_start();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Control Center - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            font-family: 'Rubik', sans-serif;
            background-color: #050505;
            color: #cbd5e1;
        }

        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s;
        }

        .glass:hover {
            border-color: rgba(59, 130, 246, 0.3);
            background: rgba(255, 255, 255, 0.05);
        }

        .s-pending {
            color: #94a3b8;
        }

        .s-running {
            color: #3b82f6;
            animation: pulse 2s infinite;
        }

        .s-completed {
            color: #22c55e;
        }

        .s-failed {
            color: #ef4444;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: 0.5
            }
        }

        ::-webkit-scrollbar {
            width: 6px
        }

        ::-webkit-scrollbar-track {
            background: #0a0a0a
        }

        ::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 10px
        }
    </style>
</head>

<body class="min-h-screen">
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
        <div class="absolute top-[-10%] left-[20%] w-[500px] h-[500px] bg-blue-900/10 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[10%] w-[400px] h-[400px] bg-purple-900/10 rounded-full blur-[100px]">
        </div>
    </div>

    <!-- Login Screen -->
    <div id="loginScreen" class="flex items-center justify-center min-h-screen p-6">
        <div class="glass w-full max-w-md p-8 rounded-3xl shadow-2xl">
            <div class="text-center mb-8">
                <div class="text-4xl mb-3">🤖</div>
                <h1 class="text-3xl font-bold text-white mb-2">Bot Control Center</h1>
                <p class="text-slate-400">היכנס כדי לנהל את הסוכנים שלך</p>
            </div>
            <div class="space-y-4 mb-6">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Server URL (מ-bot_server.py / ngrok)</label>
                    <input type="url" id="serverUrl" placeholder="https://xxxx.ngrok-free.app"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-blue-500 transition-colors font-mono">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">סיסמת מנהל</label>
                    <input type="password" id="password" placeholder="••••••••"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors">
                </div>
            </div>
            <button onclick="doLogin()"
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-blue-600/20 active:scale-95">
                התחברות
            </button>
            <div id="loginError" class="text-red-400 text-center text-sm mt-4 hidden"></div>
            <div
                class="mt-6 p-4 bg-white/[0.02] rounded-xl border border-white/5 text-xs text-slate-500 text-center leading-relaxed">
                הפעל תחילה <code class="text-blue-400">python bot_server.py</code> במחשב שלך, והכנס את ה-URL שמודפס
                במסוף
            </div>
        </div>
    </div>

    <!-- Dashboard -->
    <div id="dashboard" class="max-w-7xl mx-auto px-6 py-12 hidden">
        <header class="flex flex-col md:flex-row justify-between items-center mb-12 gap-6">
            <div>
                <h1 class="text-4xl font-bold text-white tracking-tight">🤖 Bot Control Center</h1>
                <p class="text-slate-400 mt-1">ניהול סוכני AI לאתר שלך</p>
            </div>
            <div class="flex items-center gap-4">
                <span id="serverStatus"
                    class="text-xs text-slate-500 font-mono px-3 py-1.5 bg-white/5 rounded-full border border-white/5">בודק
                    חיבור...</span>
                <button onclick="doLogout()"
                    class="px-5 py-2 rounded-xl border border-white/10 text-slate-400 hover:text-white hover:bg-white/5 transition-all text-sm">
                    <i data-lucide="log-out" class="w-4 h-4 inline-block ml-1"></i> התנתק
                </button>
            </div>
        </header>

        <!-- Bot Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 mb-12" id="botsGrid"></div>

        <!-- Jobs Table -->
        <div class="glass rounded-3xl overflow-hidden shadow-xl">
            <div class="p-6 border-b border-white/5 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">פעילות אחרונה</h3>
                <button onclick="loadJobs()" class="p-2 hover:bg-white/5 rounded-full transition-colors cursor-pointer">
                    <i data-lucide="refresh-cw" class="w-5 h-5 text-slate-400"></i>
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead class="bg-white/5 text-slate-400 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="p-4">ID</th>
                            <th class="p-4">בוט</th>
                            <th class="p-4">סטטוס</th>
                            <th class="p-4">זמן</th>
                            <th class="p-4">לוג</th>
                        </tr>
                    </thead>
                    <tbody id="jobsTableBody">
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-600">טוען...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Log Modal -->
    <div id="logModal"
        class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-6">
        <div class="glass w-full max-w-4xl max-h-[80vh] flex flex-col rounded-3xl overflow-hidden">
            <div class="p-6 border-b border-white/5 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white" id="modalTitle">Output Log</h3>
                <button onclick="document.getElementById('logModal').classList.add('hidden')"
                    class="text-slate-400 hover:text-white">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto bg-black/40 font-mono text-xs text-green-400/80 leading-relaxed whitespace-pre"
                id="modalContent"></div>
        </div>
    </div>

    <script>
        const BOTS = [
            { id: 'backup', name: 'Full Backup', desc: 'גיבוי מלא', icon: 'database' },
            { id: 'seo_analyze', name: 'SEO Analyze', desc: 'סריקת SEO', icon: 'search' },
            { id: 'seo_optimize', name: 'SEO Optimize', desc: 'שיפור SEO + העלאה', icon: 'zap' },
            { id: 'uiux_analyze', name: 'UI/UX Analyze', desc: 'סריקת עיצוב', icon: 'layout' },
            { id: 'uiux_optimize', name: 'UI/UX Optimize', desc: 'שיפור עיצוב + העלאה', icon: 'palette' },
            { id: 'security_analyze', name: 'Security Scan', desc: 'סריקת אבטחה', icon: 'shield' },
            { id: 'security_optimize', name: 'Security Harden', desc: 'הקשחת קוד + העלאה', icon: 'lock' },
            { id: 'twitter_trends', name: 'Twitter Trends', desc: 'מגמות AI בטוויטר', icon: 'trending-up' },
            { id: 'twitter_article', name: 'Article Writer', desc: 'כתיבת מאמר AI', icon: 'file-text' },
            { id: 'google_news', name: 'Google News', desc: 'הכנה ל-Google News', icon: 'globe' },
            { id: 'performance_analyze', name: 'Perf. Scan', desc: 'סריקת ביצועים', icon: 'activity' },
            { id: 'performance_optimize', name: 'Perf. Optimize', desc: 'שיפור מהירות + העלאה', icon: 'bar-chart-2' },
            { id: 'access_analyze', name: 'Access. Scan', desc: 'סריקת נגישות', icon: 'eye' },
            { id: 'access_optimize', name: 'Access. Fix', desc: 'תיקון נגישות + כפתור', icon: 'check-circle' }
        ];

        let API_BASE = '';

        function getHeaders() {
            return { 'Content-Type': 'application/json' };
        }

        async function apiGet(path) {
            return fetch(API_BASE + path).then(r => r.json());
        }

        async function apiPost(path, body) {
            return fetch(API_BASE + path, {
                method: 'POST',
                headers: getHeaders(),
                body: JSON.stringify(body)
            }).then(r => r.json());
        }

        async function doLogin() {
            const url = document.getElementById('serverUrl').value.trim().replace(/\/$/, '');
            const pass = document.getElementById('password').value;
            const errEl = document.getElementById('loginError');
            errEl.classList.add('hidden');

            if (!url) {
                errEl.textContent = 'נא להכניס את ה-URL מ-bot_server.py'; errEl.classList.remove('hidden'); return;
            }
            API_BASE = url;

            try {
                const data = await apiPost('/api/login', { password: pass });
                if (data.status === 'success') {
                    localStorage.setItem('botAdminUrl', url);
                    document.getElementById('loginScreen').classList.add('hidden');
                    document.getElementById('dashboard').classList.remove('hidden');
                    initDashboard();
                } else {
                    errEl.textContent = data.message || 'כישלון ההתחברות'; errEl.classList.remove('hidden');
                }
            } catch (e) {
                errEl.textContent = 'לא ניתן להתחבר ל-server. וודא שהוא רץ.'; errEl.classList.remove('hidden');
            }
        }

        function doLogout() {
            localStorage.removeItem('botAdminUrl');
            location.reload();
        }

        async function triggerBot(id, name) {
            if (!confirm(`להפעיל את "${name}"?`)) return;
            const data = await apiPost('/api/trigger', { bot_name: id });
            if (data.status === 'success') {
                showNotif(`✅ ${name} נוסף לתור (#${data.job_id})`);
                setTimeout(loadJobs, 1000);
            } else {
                alert(`שגיאה: ${data.message}`);
            }
        }

        function showNotif(msg) {
            const el = document.createElement('div');
            el.className = 'fixed top-6 left-1/2 -translate-x-1/2 bg-blue-600 text-white px-6 py-3 rounded-full text-sm font-medium z-50 shadow-xl transition-all';
            el.textContent = msg;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3500);
        }

        async function loadJobs() {
            const data = await apiGet('/api/jobs');
            const tbody = document.getElementById('jobsTableBody');
            if (!data.jobs || data.jobs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-600">אין עדיין פעילות</td></tr>';
                return;
            }
            tbody.innerHTML = data.jobs.map(j => `
        <tr class="border-t border-white/5 hover:bg-white/[0.02] transition-colors">
            <td class="p-4 text-slate-500 font-mono text-xs">#${j.id}</td>
            <td class="p-4 font-semibold text-white">${j.bot_name}</td>
            <td class="p-4"><span class="s-${j.status} flex items-center gap-2 text-sm font-medium">
                <span class="w-2 h-2 rounded-full bg-current"></span>${j.status.toUpperCase()}</span></td>
            <td class="p-4 text-slate-400 text-xs">${j.created_at}</td>
            <td class="p-4"><button onclick="viewLog(${j.id})" class="text-blue-400 hover:text-blue-300 text-sm">צפה</button></td>
        </tr>
    `).join('');
        }

        async function viewLog(id) {
            const data = await apiGet(`/api/job/${id}`);
            if (data.job) {
                document.getElementById('modalTitle').textContent = `${data.job.bot_name} — Output (#${id})`;
                document.getElementById('modalContent').textContent = data.job.output || 'אין פלט עדיין...';
                document.getElementById('logModal').classList.remove('hidden');
            }
        }

        async function checkServerStatus() {
            try {
                const data = await apiGet('/api/ping');
                const el = document.getElementById('serverStatus');
                if (data.running) {
                    el.textContent = '🟢 Server Online';
                    el.className = el.className.replace('text-slate-500', 'text-green-400');
                }
            } catch {
                document.getElementById('serverStatus').textContent = '🔴 Server Offline';
            }
        }

        function initDashboard() {
            const grid = document.getElementById('botsGrid');
            grid.innerHTML = BOTS.map(b => `
        <div class="glass p-5 rounded-3xl flex flex-col gap-4 cursor-pointer" onclick="triggerBot('${b.id}', '${b.name}')">
            <div class="flex justify-between items-start">
                <div class="p-2.5 bg-blue-600/15 text-blue-400 rounded-xl">
                    <i data-lucide="${b.icon}" class="w-5 h-5"></i>
                </div>
                <span class="text-[10px] font-mono text-slate-600 uppercase tracking-widest pt-1">AGENT</span>
            </div>
            <div>
                <h3 class="text-base font-bold text-white mb-1">${b.name}</h3>
                <p class="text-xs text-slate-500">${b.desc}</p>
            </div>
            <div class="mt-auto bg-white/[0.03] hover:bg-blue-600 border border-white/10 hover:border-blue-600 text-white text-xs font-medium py-2 rounded-xl text-center transition-all group">
                <i data-lucide="play" class="w-3 h-3 inline mx-1"></i> הפעל
            </div>
        </div>
    `).join('');
            lucide.createIcons();
            checkServerStatus();
            loadJobs();
            setInterval(loadJobs, 5000);
            setInterval(checkServerStatus, 15000);
        }

        // Auto-fill last used URL
        window.onload = () => {
            const saved = localStorage.getItem('botAdminUrl');
            if (saved) document.getElementById('serverUrl').value = saved;
            lucide.createIcons();
        };
    </script>
</body>

</html>