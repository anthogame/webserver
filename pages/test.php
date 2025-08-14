<?php
require_once __DIR__ . '/../includes/header.php';
if (!isLoggedIn()) { redirect($prefix.'login.php'); }
?>
<style>
  .ws-card { max-width: 820px; margin: 1.5rem auto; }
  .row { display:flex; gap:1rem; flex-wrap:wrap; }
  .row > .col { flex:1 1 240px; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
  .logbox { background: var(--bg-secondary); border:1px solid var(--border-color); padding:1rem; border-radius:.75rem; height:220px; overflow:auto; }
  .ok { color: #4CAF50; } .err { color: #e53935; } .muted { color: var(--text-secondary); }
</style>

<div class="container fade-in">
  <div class="card ws-card">
    <h1>Test WebSocket Jeu</h1>
    <p class="small-note">Connexion à <span class="mono"><?= htmlspecialchars(WS_URL) ?></span></p>

    <div class="row">
      <div class="col">
        <label>AccountId</label>
        <input id="accountId" class="form-control" type="number" placeholder="ID compte jeu">
      </div>
      <div class="col">
        <label>Montant kamas</label>
        <input id="kamas" class="form-control" type="number" placeholder="Ex: 100000">
      </div>
    </div>

    <div class="action-buttons" style="margin-top:1rem">
      <button class="btn" id="btnConnect">Se connecter au WS</button>
      <button class="btn btn-secondary" id="btnDisconnect">Se déconnecter</button>
      <button class="btn" id="btnPing">Ping</button>
      <button class="btn" id="btnIsOnline">Voir si connecté</button>
      <button class="btn" id="btnAddKamas">Add Kamas</button>
    </div>

    <h3 style="margin-top:1.25rem;">Log</h3>
    <div id="log" class="logbox mono"></div>
  </div>
</div>

<script>
(function(){
  const WS_URL  = "<?= addslashes(WS_URL) ?>";
  const WS_KEY  = "<?= addslashes(WS_API_KEY) ?>";
  let ws = null;

  const $ = sel => document.querySelector(sel);
  const logEl = $("#log");
  function log(msg, cls="muted"){
    const line = document.createElement("div");
    line.className = cls;
    line.textContent = "[" + new Date().toLocaleTimeString() + "] " + msg;
    logEl.appendChild(line);
    logEl.scrollTop = logEl.scrollHeight;
  }

  function ensureOpen(){
    if (!ws || ws.readyState !== WebSocket.OPEN){
      log("WS non connecté", "err");
      return false;
    }
    return true;
  }

  function send(action, data = {}){
    if (!ensureOpen()) return;
    const payload = { key: WS_KEY, action: action, data: data };
    ws.send(JSON.stringify(payload));
    log("Send: " + JSON.stringify(payload), "muted");
  }

  $("#btnConnect").addEventListener("click", () => {
    if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)){
      log("Déjà connecté / connexion en cours.", "muted");
      return;
    }
    try {
      ws = new WebSocket(WS_URL);
    } catch (e) {
      log("Impossible d'ouvrir le WS : " + e.message, "err");
      return;
    }

    ws.onopen = () => { log("WS connecté.", "ok"); };
    ws.onclose = () => { log("WS fermé.", "err"); };
    ws.onerror = (e) => { log("Erreur WS (voir console)", "err"); console.error(e); };
    ws.onmessage = evt => {
      try {
        const data = JSON.parse(evt.data);
        log("Recv: " + JSON.stringify(data), "muted");
      } catch (e) {
        log("Recv (raw): " + evt.data, "muted");
      }
    };
  });

  $("#btnDisconnect").addEventListener("click", () => {
    if (!ws) { log("WS non initialisé.", "err"); return; }
    try { ws.close(); } catch {}
  });

  $("#btnPing").addEventListener("click", () => {
    send("ping", {});
  });

  $("#btnIsOnline").addEventListener("click", () => {
    const accountId = parseInt(($("#accountId").value || "0"), 10);
    if (!accountId){ log("AccountId requis.", "err"); return; }
    send("isOnline", { accountId });
  });

  $("#btnAddKamas").addEventListener("click", () => {
    const accountId = parseInt(($("#accountId").value || "0"), 10);
    const amount = parseInt(($("#kamas").value || "0"), 10);
    if (!accountId){ log("AccountId requis.", "err"); return; }
    if (!amount || amount < 0){ log("Montant kamas invalide.", "err"); return; }
    send("addKamas", { accountId, amount });
  });

  // Optionnel : auto-connexion au chargement
  // document.addEventListener('DOMContentLoaded', () => $("#btnConnect").click());

})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
