#include "panneau_web.h"

#include <WebServer.h>
#include <WiFi.h>

#include "broches.h"
#include "moteur.h"

namespace {

WebServer serveur(80);
pupitre::Moteur* moteur = nullptr;

const char PAGE[] PROGMEM = R"HTML(<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pupitre des Énigmes</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#15161a;color:#eee}
header{background:#2a2c33;padding:12px 16px;font-weight:600}
main{padding:16px;max-width:640px;margin:auto}
.chrono{font-size:3em;font-weight:700;text-align:center;margin:8px 0}
.carte{background:#22242a;border-radius:10px;padding:12px;margin:10px 0}
.grille{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
button{font-size:1em;padding:12px;border:0;border-radius:8px;background:#3a5a9a;color:#fff}
button.rouge{background:#9a3a3a}
small{color:#999}
button.vert{background:#2f7d4a}
.ok{color:#5ccf86}.ko{color:#ff7a7a}.nv{color:#999}
#materiel{columns:2;font-size:.9em}
</style></head><body>
<header>Le Pupitre des Énigmes · maître du jeu</header>
<main>
<div class="chrono" id="chrono">—</div>
<div class="carte"><b id="monde">—</b> <small id="phase"></small><br><small id="zones"></small></div>
<div class="carte" id="texte"></div>
<div class="carte" id="blocage" style="display:none"><b>Épreuve en cours :</b> <span id="epreuve"></span><br>
<small>Si les joueurs sont bloqués (un capteur qui ne répond pas, par exemple), faites-la réussir.</small><br><br>
<button class="vert" onclick="if(confirm('Faire réussir l\'épreuve en cours ?'))c('debloquer')">Débloquer</button></div>
<div class="grille">
<button onclick="c('pause')">Pause</button><button onclick="c('reprise')">Reprise</button><button onclick="c('indice')">Indice</button>
<button onclick="c('temps:5')">+5 min</button><button onclick="c('temps:2')">+2 min</button><button onclick="c('temps:-2')">−2 min</button>
</div>
<div class="carte"><small>Étapes : <span id="etapes">—</span><br>Erreurs : <span id="erreurs">0</span> · Indices : <span id="indices">0</span></small></div>
<div class="carte" id="cartetiroir" style="display:none">Tiroir secret : <b id="tiroir"></b>
<button onclick="if(confirm('Ouvrir le tiroir secret ?'))c('tiroir')" style="float:right;padding:6px 10px">Ouvrir</button></div>
<div class="carte"><b>État du pupitre</b> <small>(au démarrage)</small><div id="materiel"></div></div>
<div class="grille"><button class="rouge" onclick="if(confirm('Abandonner la partie ?'))c('abandon')">Abandon</button>
<button class="rouge" onclick="if(confirm('Revenir à l\'accueil ?'))c('accueil')">Accueil</button></div>
</main>
<script>
function c(x){fetch('/commande?c='+encodeURIComponent(x))}
function maj(){fetch('/etat').then(r=>r.json()).then(e=>{
 document.getElementById('chrono').textContent=e.entete||'—';
 document.getElementById('monde').textContent=(e.monde||'Aucun monde')+(e.mission?' · mission '+e.mission:'');
 document.getElementById('phase').textContent=e.phase+(e.pause?' (pause)':'');
 document.getElementById('zones').textContent='QUI : '+(e.zones[0]||'—')+' · OÙ : '+(e.zones[1]||'—')+' · QUOI : '+(e.zones[2]||'—');
 document.getElementById('texte').textContent=e.texte||'';
 document.getElementById('etapes').textContent=(e.etapes||[]).join(', ')||'—';
 document.getElementById('erreurs').textContent=e.erreurs;
 document.getElementById('indices').textContent=e.indices;
 document.getElementById('blocage').style.display=e.epreuve?'block':'none';
 if(e.epreuve)document.getElementById('epreuve').textContent=(e.epreuve.titre||'')+' ('+e.epreuve.type+')';
 document.getElementById('cartetiroir').style.display=e.tiroir?'block':'none';
 document.getElementById('tiroir').textContent=e.tiroir||'';
 document.getElementById('materiel').innerHTML=(e.materiel||[]).map(m=>'<div class="'+(m.etat==1?'ok':m.etat==0?'ko':'nv')+'">'
   +(m.etat==1?'● ':m.etat==0?'✗ ':'○ ')+m.nom+'</div>').join('');
}).catch(()=>{})}
setInterval(maj,1000);maj();
</script></body></html>)HTML";

}  // namespace

namespace panneau {

void demarrer(pupitre::Moteur& m) {
  moteur = &m;
  WiFi.mode(WIFI_AP);
  WiFi.softAP(reglages::WIFI_NOM, reglages::WIFI_MOT_DE_PASSE);
  serveur.on("/", [] { serveur.send_P(200, "text/html; charset=utf-8", PAGE); });
  serveur.on("/etat", [] { serveur.send(200, "application/json", moteur->etatJson().c_str()); });
  serveur.on("/commande", [] {
    moteur->commande(serveur.arg("c").c_str());
    serveur.send(200, "text/plain", "ok");
  });
  serveur.begin();
  Serial.printf("Panneau : Wi-Fi « %s », http://%s\n", reglages::WIFI_NOM, WiFi.softAPIP().toString().c_str());
}

void maj() { serveur.handleClient(); }

}  // namespace panneau
