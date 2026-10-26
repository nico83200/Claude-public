#include "panneau_web.h"

#include <ArduinoOTA.h>
#include <Update.h>
#include <WebServer.h>
#include <WiFi.h>

#include "broches.h"
#include "moteur.h"

namespace {

WebServer serveur(80);
pupitre::Moteur* moteur = nullptr;
panneau::Atelier atelier;

const char PAGE[] PROGMEM = R"HTML(<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pupitre des Énigmes</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#15161a;color:#eee}
header{background:#2a2c33;padding:12px 16px;font-weight:600}
nav{display:flex;overflow-x:auto;background:#1d1f24;position:sticky;top:0}
nav button{flex:1;background:none;border-radius:0;padding:12px 10px;color:#aaa;white-space:nowrap}
nav button.actif{color:#fff;box-shadow:inset 0 -3px #5b8def}
main{padding:12px 16px;max-width:680px;margin:auto}
section{display:none}section.actif{display:block}
.chrono{font-size:3em;font-weight:700;text-align:center;margin:8px 0}
.carte{background:#22242a;border-radius:10px;padding:12px;margin:10px 0}
.grille{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
button{font-size:1em;padding:12px;border:0;border-radius:8px;background:#3a5a9a;color:#fff}
button.rouge{background:#9a3a3a}button.vert{background:#2f7d4a}button.petit{padding:6px 10px;font-size:.9em}
input,textarea{width:100%;box-sizing:border-box;font-size:1em;padding:10px;border-radius:8px;border:1px solid #444;background:#111;color:#eee;margin:4px 0}
small{color:#999}
.ok{color:#5ccf86}.ko{color:#ff7a7a}.nv{color:#999}
.colonnes{columns:2;font-size:.9em}
.ligne{display:flex;gap:6px;align-items:center;border-bottom:1px solid #2c2e35;padding:6px 0}
.ligne span{flex:1}
#journal div{font-family:ui-monospace,monospace;font-size:.85em;border-bottom:1px solid #2c2e35;padding:3px 0}
table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:5px;border-bottom:1px solid #2c2e35}td.n{text-align:right}
.mesure{display:flex;justify-content:space-between;border-bottom:1px solid #2c2e35;padding:5px 0}
</style></head><body>
<header>Le Pupitre des Énigmes · maître du jeu</header>
<nav id="onglets"></nav>
<main>

<section id="partie">
<div class="chrono" id="chrono">—</div>
<div class="carte"><b id="monde">—</b> <small id="phase"></small><br><small id="zones"></small><br><small id="equipeLigne"></small></div>
<div class="carte" id="texte"></div>
<div class="carte" id="blocage" style="display:none"><b>Épreuve en cours :</b> <span id="epreuve"></span><br>
<small>Si les joueurs sont bloqués (un capteur qui ne répond pas, par exemple), faites-la réussir.</small><br><br>
<button class="vert" onclick="if(confirm('Faire réussir l\'épreuve en cours ?'))c('debloquer')">Débloquer</button></div>
<div class="grille">
<button onclick="c('pause')">Pause</button><button onclick="c('reprise')">Reprise</button>
<button onclick="if(confirm('Donner une aide, comme la carte d\'aide ?'))c('aide')">Aide</button>
<button onclick="c('temps:5')">+5 min</button><button onclick="c('temps:2')">+2 min</button><button onclick="c('temps:-2')">−2 min</button>
</div>
<div class="carte"><small>Étapes : <span id="etapes">—</span><br>Erreurs : <span id="erreurs">0</span> · Indices : <span id="indices">0</span></small></div>
<div class="carte" id="cartetiroir" style="display:none">Tiroir secret : <b id="tiroir"></b>
<button class="petit" onclick="if(confirm('Ouvrir le tiroir secret ?'))c('tiroir')" style="float:right">Ouvrir</button></div>
<div class="carte"><b>État du pupitre</b> <small>(au démarrage)</small><div id="materiel" class="colonnes"></div></div>
<div class="grille"><button class="rouge" onclick="if(confirm('Abandonner la partie ?'))c('abandon')">Abandon</button>
<button class="rouge" onclick="if(confirm('Revenir à l\'accueil ?'))c('accueil')">Accueil</button></div>
</section>

<section id="equipe">
<div class="carte"><b>L'équipe de la partie</b><br><small>À remplir avant ou pendant la partie : elle entre au classement à la fin.</small>
<input id="nomEquipe" placeholder="Nom de l'équipe (ex. : Les Fers à cheval)">
<textarea id="prenoms" rows="6" placeholder="Prénoms, un par ligne"></textarea>
<button class="vert" onclick="equipe()">Enregistrer l'équipe</button> <small id="equipeOk"></small></div>
<div class="carte"><b>Les points</b><br><small>500 pour une victoire · 50 par étape franchie · 10 par minute restante (victoire) ·
− 50 par indice payant · − 100 par erreur d'accusation. Chaque joueur de l'équipe reçoit les points de la partie.</small></div>
</section>

<section id="souffleur">
<div class="carte"><b>Un message à l'écran</b>
<textarea id="message" rows="2" placeholder="Ex. : Avez-vous bien regardé la photo du box ?"></textarea>
<button onclick="message()">Afficher</button></div>
<div class="carte"><b>Faire dire une réplique</b> <small>(au haut-parleur 🔊, ou au casque 🎧)</small>
<input id="filtre" placeholder="Chercher (numéro, mot)…" oninput="listeRepliques()">
<div id="repliques" style="max-height:40vh;overflow-y:auto"></div></div>
<div class="carte"><b>Journal de la partie</b> <small>(le plus récent en haut ; 🎧 : ce que le joueur entend au casque)</small>
<div id="journal"></div></div>
</section>

<section id="classement">
<div class="carte"><b>Par mission</b><div id="parMission"></div></div>
<div class="carte"><b>Par joueur</b> <small>(total des points de toutes ses parties)</small><div id="parJoueur"></div></div>
</section>

<section id="atelier">
<div class="carte"><b>Essais</b><br><small>À faire hors partie : les LED et l'horloge reprennent ensuite leur rôle.</small>
<div class="grille" style="margin-top:8px">
<button onclick="e('led')">LED</button><button onclick="e('afficheur')">Horloge 88:88</button><button onclick="e('son_hp')">Son 🔊</button>
<button onclick="e('son_casque')">Son 🎧</button><button onclick="e('tiroir_ouvrir')">Tiroir : ouvrir</button><button onclick="e('tiroir_fermer')">Tiroir : verrouiller</button>
</div></div>
<div class="carte"><b>Mesures en direct</b><br><small>Pour régler les seuils (firmware/src/broches.h) : soufflez, posez l'aimant, passez la main, éclairez…</small>
<div id="mesures"></div></div>
</section>

<section id="maj">
<div class="carte"><b>Mettre à jour le programme</b><br>
<small>1. Sur l'ordinateur, compilez (PlatformIO : ✓). Le fichier est <code>firmware/.pio/build/pupitre/firmware.bin</code>.<br>
2. Connectez l'ordinateur au Wi-Fi du pupitre, ouvrez cette page, choisissez le fichier et envoyez.<br>
3. Le pupitre redémarre tout seul avec le nouveau programme. Ne coupez pas le courant pendant l'envoi.</small><br><br>
<input type="file" id="fichier" accept=".bin">
<button class="vert" onclick="televerser()">Envoyer</button>
<div id="avancee" style="margin-top:8px"></div></div>
</section>

</main>
<script>
const O=[['partie','Partie'],['equipe','Équipe'],['souffleur','Souffleur'],['classement','Classement'],['atelier','Atelier'],['maj','Mise à jour']];
let actif='partie',dernier=0,repl=[],missionVue='';
const $=i=>document.getElementById(i);
const esc=t=>String(t).replace(/[&<>"]/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[x]));
O.forEach(([i,n])=>{const b=document.createElement('button');b.textContent=n;b.onclick=()=>onglet(i);b.id='o_'+i;$('onglets').appendChild(b)});
function onglet(i){actif=i;O.forEach(([k])=>{$(k).classList.toggle('actif',k==i);$('o_'+k).classList.toggle('actif',k==i)});
 if(i=='classement')classement();if(i=='souffleur')chargerRepliques()}
onglet('partie');
function c(x){return fetch('/commande?c='+encodeURIComponent(x))}
function e(x){fetch('/essai?a='+x)}
function maj(){fetch('/etat').then(r=>r.json()).then(e=>{
 $('chrono').textContent=e.entete||'—';
 $('monde').textContent=(e.monde||'Aucun monde')+(e.mission?' · mission '+e.mission:'');
 $('phase').textContent=e.phase+(e.pause?' (pause)':'');
 $('zones').textContent='QUI : '+(e.zones[0]||'—')+' · OÙ : '+(e.zones[1]||'—')+' · QUOI : '+(e.zones[2]||'—');
 $('equipeLigne').textContent=e.equipe?'Équipe : '+e.equipe+((e.joueurs||[]).length?' ('+e.joueurs.join(', ')+')':''):'Pas encore d\'équipe';
 $('texte').textContent=e.texte||'';
 $('etapes').textContent=(e.etapes||[]).join(', ')||'—';
 $('erreurs').textContent=e.erreurs;$('indices').textContent=e.indices;
 $('blocage').style.display=e.epreuve?'block':'none';
 if(e.epreuve)$('epreuve').textContent=(e.epreuve.titre||'')+' ('+e.epreuve.type+')';
 $('cartetiroir').style.display=e.tiroir?'block':'none';$('tiroir').textContent=e.tiroir||'';
 $('materiel').innerHTML=(e.materiel||[]).map(m=>'<div class="'+(m.etat==1?'ok':m.etat==0?'ko':'nv')+'">'
   +(m.etat==1?'● ':m.etat==0?'✗ ':'○ ')+esc(m.nom)+'</div>').join('');
 const mv=(e.monde||'')+e.mission;if(mv!=missionVue){missionVue=mv;repl=[];if(actif=='souffleur')chargerRepliques()}
}).catch(()=>{})}
function journal(){fetch('/journal?depuis='+dernier).then(r=>r.json()).then(j=>{
 if(j.dernier<dernier){$('journal').innerHTML='';dernier=0}
 (j.lignes||[]).forEach(([n,t])=>{const d=document.createElement('div');d.textContent=t;$('journal').prepend(d)});
 dernier=j.dernier}).catch(()=>{})}
function equipe(){const f=new URLSearchParams();f.append('nom',$('nomEquipe').value.trim());
 f.append('joueurs',$('prenoms').value.split(/[\n,]/).map(x=>x.trim()).filter(x=>x).join('\n'));
 f.append('date',new Date().toISOString().slice(0,10));
 fetch('/equipe',{method:'POST',body:f}).then(()=>{$('equipeOk').textContent='Enregistrée ✓';maj()})}
function message(){const m=$('message').value.trim();if(m)c('message:'+m).then(()=>{$('message').value=''})}
function chargerRepliques(){if(repl.length)return listeRepliques();fetch('/repliques').then(r=>r.json()).then(l=>{repl=l;listeRepliques()})}
function listeRepliques(){const f=$('filtre').value.toLowerCase();
 $('repliques').innerHTML=repl.filter(([n,t,v])=>!f||String(n)==f||t.toLowerCase().includes(f)||(v||'').toLowerCase().includes(f)).slice(0,80)
  .map(([n,t,v])=>'<div class="ligne"><span><b>'+n+'</b> '+(v?'<small>'+esc(v)+'</small> ':'')+esc(t)+'</span>'
   +'<button class="petit" onclick="c(\'dire:'+n+'\')">🔊</button><button class="petit" onclick="c(\'casque:'+n+'\')">🎧</button></div>').join('')
  ||'<small>Aucune mission en cours.</small>'}
function classement(){fetch('/classement').then(r=>r.json()).then(d=>{const p=d.parties||[],m={},j={};
 p.forEach(x=>{const k=x.monde+' · mission '+x.mission+(x.titre?' · '+x.titre:'');(m[k]=m[k]||[]).push(x);
  (x.joueurs||[]).forEach(n=>{j[n]=j[n]||{p:0,n:0};j[n].p+=x.points;j[n].n++})});
 $('parMission').innerHTML=Object.keys(m).map(k=>'<p><b>'+esc(k)+'</b></p><table>'+m[k].sort((a,b)=>b.points-a.points).slice(0,10)
  .map((x,i)=>'<tr><td>'+(i+1)+'</td><td>'+esc(x.equipe)+'<br><small>'+esc((x.joueurs||[]).join(', '))+' · '+esc(x.date||'')
   +(x.victoire?' · réussie':' · échouée')+'</small></td><td class="n"><b>'+x.points+'</b></td></tr>').join('')+'</table>').join('')||'<small>Aucune partie.</small>';
 $('parJoueur').innerHTML='<table>'+Object.entries(j).sort((a,b)=>b[1].p-a[1].p).map(([n,v],i)=>'<tr><td>'+(i+1)+'</td><td>'+esc(n)
  +' <small>('+v.n+' partie'+(v.n>1?'s':'')+')</small></td><td class="n"><b>'+v.p+'</b></td></tr>').join('')+'</table>'})}
function mesures(){if(actif!='atelier')return;fetch('/mesures').then(r=>r.json()).then(m=>{
 $('mesures').innerHTML=Object.entries(m).map(([k,v])=>'<div class="mesure"><span>'+esc(k)+'</span><b>'+esc(v)+'</b></div>').join('')}).catch(()=>{})}
function televerser(){const f=$('fichier').files[0];if(!f)return;const x=new XMLHttpRequest(),d=new FormData();d.append('firmware',f);
 x.upload.onprogress=v=>{$('avancee').textContent='Envoi : '+Math.round(100*v.loaded/v.total)+' %'};
 x.onload=()=>{$('avancee').textContent=x.responseText};x.onerror=()=>{$('avancee').textContent='Échec de l\'envoi'};
 x.open('POST','/maj');x.send(d)}
setInterval(maj,1000);setInterval(journal,1000);setInterval(mesures,500);maj();journal();
</script></body></html>)HTML";

}  // namespace

namespace panneau {

void demarrer(pupitre::Moteur& m, const Atelier& a) {
  moteur = &m;
  atelier = a;
  WiFi.mode(WIFI_AP);
  WiFi.softAP(reglages::WIFI_NOM, reglages::WIFI_MOT_DE_PASSE);
  serveur.on("/", [] { serveur.send_P(200, "text/html; charset=utf-8", PAGE); });
  serveur.on("/etat", [] { serveur.send(200, "application/json", moteur->etatJson().c_str()); });
  serveur.on("/journal", [] { serveur.send(200, "application/json", moteur->journalJson(serveur.arg("depuis").toInt()).c_str()); });
  serveur.on("/repliques", [] { serveur.send(200, "application/json", moteur->repliquesJson().c_str()); });
  serveur.on("/classement", [] { serveur.send(200, "application/json", moteur->classementJson().c_str()); });
  serveur.on("/commande", [] {
    moteur->commande(serveur.arg("c").c_str());
    serveur.send(200, "text/plain", "ok");
  });
  serveur.on("/equipe", HTTP_POST, [] {
    std::vector<std::string> joueurs;
    String liste = serveur.arg("joueurs");
    int debut = 0;
    while (debut <= static_cast<int>(liste.length())) {
      int fin = liste.indexOf('\n', debut);
      if (fin < 0) fin = liste.length();
      String j = liste.substring(debut, fin);
      j.trim();
      if (j.length()) joueurs.push_back(j.c_str());
      debut = fin + 1;
    }
    moteur->definirEquipe(serveur.arg("nom").c_str(), joueurs, serveur.arg("date").c_str());
    serveur.send(200, "text/plain", "ok");
  });
  serveur.on("/mesures", [] { serveur.send(200, "application/json", atelier.mesures ? atelier.mesures().c_str() : "{}"); });
  serveur.on("/essai", [] {
    if (atelier.essai) atelier.essai(serveur.arg("a").c_str());
    serveur.send(200, "text/plain", "ok");
  });

  // La mise à jour du programme : le fichier firmware.bin envoyé depuis la page.
  serveur.on(
      "/maj", HTTP_POST,
      [] {
        bool ok = !Update.hasError();
        serveur.send(200, "text/plain; charset=utf-8",
                     ok ? "Programme reçu : le pupitre redémarre…" : "Échec : le programme n'a pas été installé.");
        if (ok) {
          delay(500);
          ESP.restart();
        }
      },
      [] {
        HTTPUpload& envoi = serveur.upload();
        if (envoi.status == UPLOAD_FILE_START) {
          Serial.printf("Mise à jour : %s\n", envoi.filename.c_str());
          Update.begin(UPDATE_SIZE_UNKNOWN);
        } else if (envoi.status == UPLOAD_FILE_WRITE) {
          Update.write(envoi.buf, envoi.currentSize);
        } else if (envoi.status == UPLOAD_FILE_END) {
          Serial.printf("Mise à jour : %s (%u octets)\n", Update.end(true) ? "réussie" : "ÉCHEC", envoi.totalSize);
        }
      });
  serveur.begin();

  // La mise à jour depuis PlatformIO, par le Wi-Fi : « pio run -e pupitre-wifi -t upload ».
  ArduinoOTA.setHostname("pupitre");
  ArduinoOTA.setPassword(reglages::WIFI_MOT_DE_PASSE);
  ArduinoOTA.begin();
  Serial.printf("Panneau : Wi-Fi « %s », http://%s\n", reglages::WIFI_NOM, WiFi.softAPIP().toString().c_str());
}

void maj() {
  serveur.handleClient();
  ArduinoOTA.handle();
}

}  // namespace panneau
