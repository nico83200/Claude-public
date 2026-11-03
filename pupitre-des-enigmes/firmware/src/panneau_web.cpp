#include "panneau_web.h"

#include <ArduinoOTA.h>
#include <SD.h>
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
.miroir{position:relative;aspect-ratio:3/2;border-radius:10px;overflow:hidden;padding:8px 10px;box-sizing:border-box;display:flex;flex-direction:column;font-size:.85em;border:3px solid #000}
.miroir .haut{display:flex;justify-content:space-between;opacity:.85;font-size:.85em;border-bottom:1px solid rgba(255,255,255,.2);padding-bottom:3px}
.miroir .titre{font-weight:700;margin:6px 0 4px}
.miroir .corps{flex:1;overflow:hidden;white-space:pre-wrap;line-height:1.3}
.miroir .bas{font-size:.85em;opacity:.8;min-height:1.2em}
.miroir .sel{border-radius:4px;padding:0 4px}
.miroir .opts{display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-top:4px}.miroir .opts div{border-radius:4px;padding:2px 6px;color:#fff}
.badge{display:inline-block;border-radius:6px;padding:1px 7px;font-size:.8em;margin-left:6px}
.b-suiv{background:#c9a54a;color:#111}.b-actif{background:#3a5a9a}.b-fini{background:#2f7d4a}.b-non{background:#333;color:#999}
.indice{border-left:3px solid #444;padding:4px 8px;margin:6px 0}.indice.donne{border-color:#2f7d4a;opacity:.7}
.barre{height:8px;background:#5b8def;border-radius:4px}
</style></head><body>
<header>Le Pupitre des Énigmes · maître du jeu</header>
<div id="codeDefaut" style="display:none;background:#9a3a3a;padding:8px 16px">⚠ Le panneau a encore son code d'origine :
changez-le (onglet Atelier) avant la première partie.</div>
<nav id="onglets"></nav>
<main>

<section id="partie">
<div class="chrono" id="chrono">—</div>
<div class="carte"><b id="monde">—</b> <small id="phase"></small><br><small id="zones"></small><br><small id="equipeLigne"></small></div>
<div class="carte"><b>L'écran du pupitre</b> <small>(en miroir, chaque seconde)</small>
<div class="miroir" id="miroir"></div></div>
<div class="carte" id="souffleurAuto" style="display:none"></div>
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
<div class="carte"><b>Les indices tout prêts</b> <small id="indicesResume"></small><br>
<small>Le blocage marqué <span class="badge b-suiv">suivant</span> est celui que donnerait la carte d'aide.
🔊 haut-parleur · 🎧 casque · ✉ écrire à l'écran.</small>
<div id="indicesListe"></div>
<button onclick="if(confirm('Donner l\'indice suivant, comme la carte d\'aide ?'))c('aide').then(indices)">Donner l'indice suivant</button></div>
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

<section id="stats">
<div class="carte"><b>Statistiques des parties</b><br><small>Calculées sur toutes les parties du classement. L'étape la plus
longue est celle qui fait le plus souvent caler les équipes : c'est là qu'un indice ou une retouche du script aide le plus.</small></div>
<div id="statsListe"></div>
</section>

<section id="missions">
<div class="carte"><b>Envoyer une mission par le Wi-Fi</b><br>
<small>Sans retirer la carte SD de l'écran.<br>
1. Sur l'ordinateur ou la tablette, décompressez <code>carte-sd-ecran.zip</code>.<br>
2. Choisissez le dossier décompressé (celui qui contient <code>mondes</code>) : seuls les fichiers des dossiers
<code>mondes</code> et <code>pupitre</code> partent sur le pupitre.<br>
3. Les cartes déjà enregistrées (<code>cartes.json</code>) sont gardées. Le pupitre relit ensuite ses mondes
(hors partie seulement).</small><br><br>
<input type="file" id="dossier" webkitdirectory directory multiple>
<button class="vert" onclick="envoyerMission()">Envoyer</button>
<div id="envoi" style="margin-top:8px"></div><div id="envoiDetail" style="max-height:30vh;overflow-y:auto;font-size:.85em"></div></div>
</section>

<section id="atelier">
<div class="carte"><b>Essais</b><br><small>À faire hors partie : les LED et l'horloge reprennent ensuite leur rôle.</small>
<div class="grille" style="margin-top:8px">
<button onclick="e('led')">LED</button><button onclick="e('afficheur')">Horloge 88:88</button><button onclick="e('son_hp')">Son 🔊</button>
<button onclick="e('son_casque')">Son 🎧</button><button onclick="e('tiroir_ouvrir')">Tiroir : ouvrir</button><button onclick="e('tiroir_fermer')">Tiroir : verrouiller</button><button onclick="e('tactile')">Étalonner l'écran tactile</button>
</div></div>
<div class="carte"><b>Code d'accès du panneau</b><br><small>4 à 8 chiffres. Il est gardé sur la carte SD de l'écran
(<code>code-panneau.txt</code> : effacez ce fichier pour revenir au code d'origine).</small>
<input id="codeAncien" type="password" inputmode="numeric" placeholder="Code actuel">
<input id="codeNouveau" type="password" inputmode="numeric" placeholder="Nouveau code">
<button onclick="changerCode()">Changer le code</button> <button class="rouge" onclick="location='/deconnexion'">Se déconnecter</button>
<div id="codeOk" style="margin-top:6px"></div></div>
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
const f0=window.fetch;window.fetch=(...a)=>f0(...a).then(r=>{if(r.status==401)location.reload();return r});
const O=[['partie','Partie'],['equipe','Équipe'],['souffleur','Souffleur'],['classement','Classement'],['stats','Statistiques'],['missions','Missions'],['atelier','Atelier'],['maj','Mise à jour']];
let actif='partie',dernier=0,repl=[],missionVue='';
const $=i=>document.getElementById(i);
const esc=t=>String(t).replace(/[&<>"]/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[x]));
O.forEach(([i,n])=>{const b=document.createElement('button');b.textContent=n;b.onclick=()=>onglet(i);b.id='o_'+i;$('onglets').appendChild(b)});
function onglet(i){actif=i;O.forEach(([k])=>{$(k).classList.toggle('actif',k==i);$('o_'+k).classList.toggle('actif',k==i)});
 if(i=='classement')classement();if(i=='souffleur'){chargerRepliques();indices()}if(i=='stats')stats()}
onglet('partie');
function c(x){return fetch('/commande?c='+encodeURIComponent(x))}
function e(x){fetch('/essai?a='+x)}
function maj(){fetch('/etat').then(r=>r.json()).then(e=>{
 $('chrono').textContent=e.entete||'—';
 $('monde').textContent=(e.monde||'Aucun monde')+(e.mission?' · mission '+e.mission:'');
 $('phase').textContent=e.phase+(e.pause?' (pause)':'');
 $('zones').textContent='QUI : '+(e.zones[0]||'—')+' · OÙ : '+(e.zones[1]||'—')+' · QUOI : '+(e.zones[2]||'—');
 $('equipeLigne').textContent=e.equipe?'Équipe : '+e.equipe+((e.joueurs||[]).length?' ('+e.joueurs.join(', ')+')':''):'Pas encore d\'équipe';
 miroir(e.ecran||{});
 const sa=$('souffleurAuto');sa.style.display=e.sansAvancee!=null?'block':'none';
 if(e.sansAvancee!=null)sa.innerHTML='<small>Dernière avancée des joueurs il y a <b>'+Math.floor(e.sansAvancee/60)+' min</b>'
  +(e.ecran&&e.ecran.aide?' · <span class="ko">la carte d\'aide clignote</span>':'')+'</small>';
 $('etapes').textContent=(e.etapes||[]).join(', ')||'—';
 $('erreurs').textContent=e.erreurs;$('indices').textContent=e.indices;
 $('blocage').style.display=e.epreuve?'block':'none';
 if(e.epreuve)$('epreuve').textContent=(e.epreuve.titre||'')+' ('+e.epreuve.type+')';
 $('cartetiroir').style.display=e.tiroir?'block':'none';$('tiroir').textContent=e.tiroir||'';
 $('materiel').innerHTML=(e.materiel||[]).map(m=>'<div class="'+(m.etat==1?'ok':m.etat==0?'ko':'nv')+'">'
   +(m.etat==1?'● ':m.etat==0?'✗ ':'○ ')+esc(m.nom)+'</div>').join('');
 const mv=(e.monde||'')+e.mission;if(mv!=missionVue){missionVue=mv;repl=[];if(actif=='souffleur')chargerRepliques()}
}).catch(()=>{})}
const COUL=['#d02020','#2050e0','#e0b000','#20b040'];
function miroir(v){const m=$('miroir');m.style.background=v.fond||'#1f3b2d';m.style.color=v.couleur||'#f3ead8';
 const ac=v.accent||'#c9a54a';let corps='';
 if(v.menu&&v.menu.length)corps=v.menu.map((l,i)=>'<div'+(i==v.curseur?' class="sel" style="background:'+ac+';color:'+(v.fond||'#000')+'"':'')+'>'+esc(l)+'</div>').join('');
 else corps=esc(v.texte||'');
 if(v.champs&&v.champs.length)corps+='<div style="font-size:1.6em;letter-spacing:.3em;text-align:center;margin-top:6px">'+esc(v.champs.join(' '))+'</div>';
 if(v.saisie!=null)corps+='<div style="font-size:1.3em;margin-top:6px">'+esc(v.saisie)+'▌</div>';
 if(v.options&&v.options.some(o=>o))corps+='<div class="opts">'+v.options.map((o,i)=>o?'<div style="background:'+COUL[i]+'">'+esc(o)+'</div>':'').join('')+'</div>';
 if(v.type=='tableau'&&v.objectifs)corps=v.objectifs.map(([n,f])=>(f==2?'✓ ':'· ')+esc(n)).join('<br>');
 m.innerHTML='<div class="haut"><span>'+esc(v.mission||'')+(v.compteurs?' · '+esc(v.compteurs):'')+'</span><b>'+esc(v.entete||'')+'</b></div>'
  +'<div class="titre" style="color:'+ac+'">'+esc(v.titre||'')+(v.aide?' <span style="color:#ff7a7a">● aide</span>':'')+'</div>'
  +'<div class="corps">'+corps+'</div><div class="bas">'+esc(v.info||v.bandeau||'')+(v.secondes!=null?' · ⏱ '+v.secondes+' s':'')+'</div>'}
function indices(){if(actif!='souffleur')return;fetch('/indices').then(r=>r.json()).then(d=>{
 $('indicesResume').textContent=d.mission?'· '+d.donnes+' donné(s), '+d.gratuits+' gratuit(s) restant(s)':'';
 $('indicesListe').innerHTML=(d.blocages||[]).map((b,i)=>{
  const etat=b.suivant?'<span class="badge b-suiv">suivant</span>':b.actif?'<span class="badge b-actif">à venir</span>':'<span class="badge b-non">pas maintenant</span>';
  return '<div class="carte" style="background:#1b1d22"><b>Blocage '+(i+1)+'</b>'+etat+' <small>('+esc(b.si)+')</small>'
   +(b.niveaux||[]).map((n,k)=>'<div class="indice'+(k<b.donnes?' donne':'')+'"><small>Niveau '+(k+1)+(k<b.donnes?' · déjà donné':'')+' · réplique '+n.n+'</small><br>'
    +esc(n.texte)+'<br><button class="petit" onclick="c(\'dire:'+n.n+'\')">🔊</button> <button class="petit" onclick="c(\'casque:'+n.n+'\')">🎧</button> '
    +'<button class="petit" onclick="ecrireIndice('+n.n+')">✉</button></div>').join('')+'</div>'}).join('')
  ||'<small>Aucune mission en cours.</small>';indicesCache=d}).catch(()=>{})}
let indicesCache={};
function ecrireIndice(n){const t=replTexte(n);if(t&&confirm('Écrire cet indice à l\'écran ?'))c('message:'+t)}
function replTexte(n){for(const b of indicesCache.blocages||[])for(const x of b.niveaux||[])if(x.n==n)return x.texte;return ''}
function mmss(s){s=Math.round(s||0);return Math.floor(s/60)+' min '+String(s%60).padStart(2,'0')}
function stats(){fetch('/classement').then(r=>r.json()).then(d=>{const g={};
 (d.parties||[]).forEach(x=>{const k=x.monde+' · '+(x.titre||'');(g[k]=g[k]||[]).push(x)});
 $('statsListe').innerHTML=Object.keys(g).map(k=>{const p=g[k],n=p.length,v=p.filter(x=>x.victoire),moy=(a,f)=>a.length?a.reduce((s,x)=>s+(f(x)||0),0)/a.length:0;
  const et={},ordre=[];p.forEach(x=>{let avant=0;(x.etapes||[]).forEach(e=>{if(!et[e.nom]){et[e.nom]=[];ordre.push(e.nom)}et[e.nom].push(e.t-avant);avant=e.t})});
  const lignes=ordre.map(e=>[e,moy(et[e],x=>x),et[e].length]);const max=Math.max(1,...lignes.map(l=>l[1]));const pire=lignes.reduce((a,l)=>l[1]>(a?a[1]:-1)?l:a,null);
  return '<div class="carte"><b>'+esc(k)+'</b><br><small>'+n+' partie'+(n>1?'s':'')+' · réussite '+Math.round(100*v.length/n)+' %'
   +(v.length?' · durée moyenne (victoires) '+mmss(moy(v,x=>x.duree)):'')+' · indices '+moy(p,x=>x.aides!=null?x.aides:x.indices).toFixed(1)
   +' · souffleur auto '+moy(p,x=>x.souffleur).toFixed(1)+'</small>'
   +(pire?'<p>Étape la plus longue : <b class="ko">'+esc(pire[0])+'</b> ('+mmss(pire[1])+' en moyenne)</p>':'')
   +'<table>'+lignes.map(([e,t,c])=>'<tr><td>'+esc(e)+'<div class="barre" style="width:'+Math.round(100*t/max)+'%"></div></td><td class="n">'+mmss(t)+'<br><small>'+c+' équipe'+(c>1?'s':'')+'</small></td></tr>').join('')+'</table></div>'}).join('')
  ||'<div class="carte"><small>Aucune partie enregistrée.</small></div>'})}
async function envoyerMission(){const f=[...$('dossier').files].map(x=>{const r=x.webkitRelativePath||x.name;const i=r.search(/(^|\/)(mondes|pupitre)\//);
  return i<0?null:{f:x,chemin:'/'+r.slice(i).replace(/^\//,'')}}).filter(x=>x&&/\.(json|jpg|png)$/i.test(x.chemin));
 if(!f.length){$('envoi').textContent='Aucun fichier des dossiers mondes ou pupitre dans ce dossier.';return}
 $('envoiDetail').innerHTML='';let ok=0,garde=0,ko=0;
 for(let i=0;i<f.length;i++){$('envoi').textContent='Envoi '+(i+1)+' / '+f.length+'…';const d=new FormData();d.append('fichier',f[i].f);
  let t;try{t=await (await fetch('/fichier?chemin='+encodeURIComponent(f[i].chemin),{method:'POST',body:d})).text()}catch(e){t='ko: réseau'}
  if(t.startsWith('ok'))ok++;else if(t.startsWith('garde'))garde++;else ko++;
  const l=document.createElement('div');l.className=t.startsWith('ok')?'ok':t.startsWith('garde')?'nv':'ko';l.textContent=f[i].chemin+' — '+t;$('envoiDetail').prepend(l)}
 await c('recharger');
 $('envoi').innerHTML='<b>'+ok+' fichier(s) envoyé(s)</b>'+(garde?' · '+garde+' gardé(s) (cartes déjà enregistrées)':'')+(ko?' · <span class="ko">'+ko+' en échec</span>':'')
  +'<br><small>Le pupitre a relu ses mondes (s\'il n\'était pas en partie). Pour un nouveau monde : enregistrez ses cartes depuis son menu.</small>'}
function changerCode(){const f=new URLSearchParams();f.append('ancien',$('codeAncien').value);f.append('nouveau',$('codeNouveau').value);
 fetch('/code',{method:'POST',body:f}).then(r=>r.text()).then(t=>{$('codeOk').textContent=t;$('codeAncien').value=$('codeNouveau').value='';verifierCode()})}
function verifierCode(){fetch('/code').then(r=>r.json()).then(d=>{$('codeDefaut').style.display=d.defaut?'block':'none'}).catch(()=>{})}
verifierCode();
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
setInterval(maj,1000);setInterval(journal,1000);setInterval(mesures,500);setInterval(indices,3000);maj();journal();
</script></body></html>)HTML";

// Le diplôme de l'équipe, sur le téléphone des joueurs (QR code de l'écran de victoire).
// Une page à part : elle ne mène pas au panneau du maître du jeu.
const char DIPLOME[] PROGMEM = R"HTML(<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diplôme · Le Pupitre des Énigmes</title>
<style>
body{margin:0;background:#2b2118;font-family:Georgia,'Times New Roman',serif;color:#3b2a14;display:flex;justify-content:center;align-items:flex-start;padding:16px;box-sizing:border-box;min-height:100vh}
.d{background:radial-gradient(circle at 50% 30%,#fff8e6,#f1dfb4);max-width:560px;width:100%;border:10px double #b08a3e;border-radius:6px;padding:28px 22px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.5)}
.sur{letter-spacing:.3em;font-size:.8em;color:#8a6a2a}
h1{font-size:2.4em;margin:.2em 0;color:#7a4f12}
.equipe{font-size:1.8em;font-weight:700;margin:.4em 0;color:#2c1d0a}
.joueurs{font-style:italic;margin-bottom:1em}
.titre{display:inline-block;background:#7a4f12;color:#fff4d6;border-radius:30px;padding:6px 18px;margin:.6em 0;font-size:1.1em}
.chiffres{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:1em 0}
.chiffres div{border:1px solid #c9a54a;border-radius:8px;padding:8px 12px;min-width:80px}
.chiffres b{display:block;font-size:1.4em;color:#7a4f12}
.sceau{font-size:2.6em;margin-top:.3em}
small{color:#8a6a2a}
button{font-family:inherit;font-size:1em;margin-top:14px;padding:10px 18px;border:0;border-radius:8px;background:#7a4f12;color:#fff}
@media print{body{background:#fff}button{display:none}.d{box-shadow:none}}
</style></head><body><div class="d" id="d"><p>Le pupitre prépare votre diplôme…</p></div>
<script>
const esc=t=>String(t==null?'':t).replace(/[&<>"]/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[x]));
fetch('/diplome.json').then(r=>r.json()).then(p=>{if(!p.equipe){document.getElementById('d').innerHTML='<p>Pas encore de diplôme : terminez d\'abord une mission !</p>';return}
 const t=s=>Math.floor(s/60)+' min '+String(s%60).padStart(2,'0');
 document.getElementById('d').innerHTML='<div class="sur">LE PUPITRE DES ÉNIGMES</div><h1>Diplôme</h1><small>décerné à</small>'
  +'<div class="equipe">'+esc(p.equipe)+'</div>'+((p.joueurs||[]).length?'<div class="joueurs">'+esc(p.joueurs.join(' · '))+'</div>':'')
  +'<div>pour avoir réussi la mission</div><div style="font-size:1.4em;margin:.3em 0"><b>« '+esc(p.mission)+' »</b></div><small>'+esc(p.monde)+'</small><br>'
  +(p.titre?'<div class="titre">★ '+esc(p.titre)+' ★</div>':'')
  +'<div class="chiffres"><div><b>'+t(p.duree||0)+'</b>de jeu</div><div><b>'+t(p.restant||0)+'</b>d\'avance</div><div><b>'+p.points+'</b>points</div>'
  +'<div><b>'+p.rang+(p.rang==1?'er':'e')+'</b>au classement</div></div>'
  +'<div class="sceau">🏅</div><small>'+esc(p.date?new Date(p.date+'T12:00').toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'}):'')+'</small><br><button onclick="print()">Imprimer ou enregistrer en PDF</button>'})
 .catch(()=>{document.getElementById('d').textContent='Le pupitre ne répond pas.'});
</script></body></html>)HTML";

// Le code d'accès du panneau. Le diplôme (/diplome, /diplome.json) reste ouvert : les joueurs
// rejoignent le Wi-Fi par le QR code de l'écran de victoire, ils ne doivent rien voir d'autre.
const char* const CHEMIN_CODE = "/code-panneau.txt";
constexpr int ESSAIS_MAX = 5;
String codePanneau, jeton;
int echecs = 0;
uint32_t bloqueJusqua = 0;
bool majAutorisee = false;

bool autorise() { return jeton.length() && serveur.header("Cookie").indexOf("jeton=" + jeton) >= 0; }

bool garde() {
  if (autorise()) return true;
  serveur.send(401, "text/plain; charset=utf-8", "Code d'accès requis");
  return false;
}

const char CONNEXION[] PROGMEM = R"HTML(<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pupitre des Énigmes</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#15161a;color:#eee;display:flex;justify-content:center;padding:40px 16px}
form{background:#22242a;border-radius:12px;padding:20px;max-width:320px;width:100%;text-align:center}
input{width:100%;box-sizing:border-box;font-size:2em;letter-spacing:.3em;text-align:center;padding:10px;border-radius:8px;border:1px solid #444;background:#111;color:#eee;margin:12px 0}
button{width:100%;font-size:1.1em;padding:12px;border:0;border-radius:8px;background:#2f7d4a;color:#fff}
p{color:#ff7a7a;min-height:1.2em}small{color:#999}
</style></head><body>
<form method="post" action="/connexion"><b>Le Pupitre des Énigmes</b><br><small>Panneau du maître du jeu</small>
<input name="code" type="password" inputmode="numeric" autocomplete="current-password" placeholder="Code" autofocus required>
<button>Entrer</button><p id="m"></p></form>
<script>const q=location.search;document.getElementById('m').textContent=q.includes('erreur')?'Code incorrect.':q.includes('attente')?'Trop d\'essais : attendez une minute.':''</script>
</body></html>)HTML";

// L'envoi d'une mission par le Wi-Fi : les fichiers des dossiers /mondes et /pupitre de la carte SD.
File fichierRecu;
String cheminRecu, reponseEnvoi;

bool cheminPermis(const String& c) {
  if (!c.startsWith("/mondes/") && !c.startsWith("/pupitre/")) return false;
  if (c.indexOf("..") >= 0 || c.indexOf("//") >= 0 || c.indexOf('\\') >= 0 || c.length() > 96) return false;
  String m = c;
  m.toLowerCase();
  return m.endsWith(".json") || m.endsWith(".jpg") || m.endsWith(".png");
}

void creerDossiers(const String& c) {
  for (int i = c.indexOf('/', 1); i > 0; i = c.indexOf('/', i + 1)) {
    String d = c.substring(0, i);
    if (!SD.exists(d)) SD.mkdir(d);
  }
}

}  // namespace

namespace panneau {

void demarrer(pupitre::Moteur& m, const Atelier& a) {
  moteur = &m;
  atelier = a;
  WiFi.mode(WIFI_AP);
  WiFi.softAP(reglages::WIFI_NOM, reglages::WIFI_MOT_DE_PASSE);
  // Le code d'accès : celui de la carte SD s'il a été changé, sinon celui de broches.h.
  codePanneau = reglages::CODE_PANNEAU;
  File f = SD.open(CHEMIN_CODE);
  if (f) {
    String c = f.readString();
    c.trim();
    if (c.length() >= 4) codePanneau = c;
    f.close();
  }
  // Un jeton tiré au sort à chaque démarrage : le navigateur le garde une fois le code donné.
  char hex[17];
  snprintf(hex, sizeof hex, "%08lx%08lx", static_cast<unsigned long>(esp_random()), static_cast<unsigned long>(esp_random()));
  jeton = hex;
  static const char* entetes[] = {"Cookie"};
  serveur.collectHeaders(entetes, 1);
  // Le panneau : la page de connexion tant que le navigateur n'a pas donné le bon code.
  serveur.on("/", [] {
    if (autorise()) serveur.send_P(200, "text/html; charset=utf-8", PAGE);
    else serveur.send_P(200, "text/html; charset=utf-8", CONNEXION);
  });
  serveur.on("/connexion", HTTP_POST, [] {
    if (millis() < bloqueJusqua) {
      serveur.sendHeader("Location", "/?attente=1");
    } else if (serveur.arg("code") == codePanneau) {
      echecs = 0;
      serveur.sendHeader("Set-Cookie", "jeton=" + jeton + "; Path=/; HttpOnly; SameSite=Strict");
      serveur.sendHeader("Location", "/");
    } else {
      if (++echecs >= ESSAIS_MAX) {  // trop d'essais : une minute de pause, pour tout le monde
        echecs = 0;
        bloqueJusqua = millis() + 60000;
      }
      Serial.println("Panneau : mauvais code d'accès");
      serveur.sendHeader("Location", "/?erreur=1");
    }
    serveur.send(303);
  });
  serveur.on("/deconnexion", [] {
    serveur.sendHeader("Set-Cookie", "jeton=; Path=/; Max-Age=0");
    serveur.sendHeader("Location", "/");
    serveur.send(303);
  });
  // Changer le code (onglet Atelier) : gardé sur la carte SD de l'écran.
  serveur.on("/code", HTTP_GET, [] {
    if (!garde()) return;
    serveur.send(200, "application/json", codePanneau == reglages::CODE_PANNEAU ? "{\"defaut\":true}" : "{\"defaut\":false}");
  });
  serveur.on("/code", HTTP_POST, [] {
    if (!garde()) return;
    String ancien = serveur.arg("ancien"), nouveau = serveur.arg("nouveau");
    bool chiffres = nouveau.length() >= 4 && nouveau.length() <= 8;
    for (char ch : nouveau) chiffres = chiffres && ch >= '0' && ch <= '9';
    if (ancien != codePanneau) return serveur.send(200, "text/plain; charset=utf-8", "Le code actuel n'est pas le bon.");
    if (!chiffres) return serveur.send(200, "text/plain; charset=utf-8", "Le nouveau code : 4 à 8 chiffres.");
    File f = SD.open(CHEMIN_CODE, FILE_WRITE);
    if (!f) return serveur.send(200, "text/plain; charset=utf-8", "Carte SD absente : code non enregistré.");
    f.print(nouveau);
    f.close();
    codePanneau = nouveau;
    serveur.send(200, "text/plain; charset=utf-8", "Nouveau code enregistré ✓");
  });
  serveur.on("/etat", [] {
    if (!garde()) return;
    serveur.send(200, "application/json", moteur->etatJson().c_str()); });
  serveur.on("/journal", [] {
    if (!garde()) return;
    serveur.send(200, "application/json", moteur->journalJson(serveur.arg("depuis").toInt()).c_str()); });
  serveur.on("/repliques", [] {
    if (!garde()) return;
    serveur.send(200, "application/json", moteur->repliquesJson().c_str()); });
  serveur.on("/classement", [] {
    if (!garde()) return;
    serveur.send(200, "application/json", moteur->classementJson().c_str()); });
  serveur.on("/indices", [] {
    if (!garde()) return;
    serveur.send(200, "application/json", moteur->indicesJson().c_str()); });
  serveur.on("/diplome", [] { serveur.send_P(200, "text/html; charset=utf-8", DIPLOME); });
  serveur.on("/diplome.json", [] { serveur.send(200, "application/json", moteur->diplomeJson().c_str()); });
  // Un fichier de mission, écrit d'abord à côté (.tmp) puis mis en place une fois complet.
  // Les cartes déjà enregistrées (cartes.json) ne sont jamais écrasées.
  serveur.on(
      "/fichier", HTTP_POST,
      [] {
        if (garde()) serveur.send(200, "text/plain; charset=utf-8", reponseEnvoi);
      },
      [] {
        HTTPUpload& envoi = serveur.upload();
        if (envoi.status == UPLOAD_FILE_START) {
          cheminRecu = serveur.arg("chemin");
          if (!autorise()) {
            reponseEnvoi = "ko : code d'accès";
            cheminRecu = "";
          } else if (!cheminPermis(cheminRecu)) {
            reponseEnvoi = "ko : chemin refusé";
            cheminRecu = "";
          } else if (cheminRecu.endsWith("/cartes.json") && SD.exists(cheminRecu)) {
            reponseEnvoi = "gardé : les cartes de ce monde sont déjà enregistrées";
            cheminRecu = "";
          } else {
            creerDossiers(cheminRecu);
            fichierRecu = SD.open(cheminRecu + ".tmp", FILE_WRITE);
            reponseEnvoi = fichierRecu ? "ok" : "ko : carte SD";
            if (!fichierRecu) cheminRecu = "";
          }
        } else if (envoi.status == UPLOAD_FILE_WRITE && fichierRecu) {
          if (fichierRecu.write(envoi.buf, envoi.currentSize) != envoi.currentSize) reponseEnvoi = "ko : carte SD pleine ?";
        } else if (envoi.status == UPLOAD_FILE_END && fichierRecu) {
          fichierRecu.close();
          if (reponseEnvoi == "ok") {
            SD.remove(cheminRecu);
            if (!SD.rename(cheminRecu + ".tmp", cheminRecu)) reponseEnvoi = "ko : renommage";
          } else {
            SD.remove(cheminRecu + ".tmp");
          }
          Serial.printf("Panneau : %s reçu (%u octets) : %s\n", cheminRecu.c_str(), envoi.totalSize, reponseEnvoi.c_str());
        } else if (envoi.status == UPLOAD_FILE_ABORTED && fichierRecu) {
          fichierRecu.close();
          SD.remove(cheminRecu + ".tmp");
          reponseEnvoi = "ko : envoi interrompu";
        }
      });
  serveur.on("/commande", [] {
    if (!garde()) return;
    moteur->commande(serveur.arg("c").c_str());
    serveur.send(200, "text/plain", "ok");
  });
  serveur.on("/equipe", HTTP_POST, [] {
    if (!garde()) return;
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
  serveur.on("/mesures", [] {
    if (!garde()) return;
    serveur.send(200, "application/json", atelier.mesures ? atelier.mesures().c_str() : "{}"); });
  serveur.on("/essai", [] {
    if (!garde()) return;
    if (atelier.essai) atelier.essai(serveur.arg("a").c_str());
    serveur.send(200, "text/plain", "ok");
  });

  // La mise à jour du programme : le fichier firmware.bin envoyé depuis la page.
  serveur.on(
      "/maj", HTTP_POST,
      [] {
        if (!garde()) return;
        bool ok = majAutorisee && !Update.hasError();
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
          majAutorisee = autorise();  // sans le code, le fichier est ignoré
          if (!majAutorisee) return;
          Serial.printf("Mise à jour : %s\n", envoi.filename.c_str());
          Update.begin(UPDATE_SIZE_UNKNOWN);
        } else if (!majAutorisee) {
          return;
        } else if (envoi.status == UPLOAD_FILE_WRITE) {
          Update.write(envoi.buf, envoi.currentSize);
        } else if (envoi.status == UPLOAD_FILE_END) {
          Serial.printf("Mise à jour : %s (%u octets)\n", Update.end(true) ? "réussie" : "ÉCHEC", envoi.totalSize);
        }
      });
  serveur.begin();

  // La mise à jour depuis PlatformIO, par le Wi-Fi : « pio run -e pupitre-wifi -t upload ».
  ArduinoOTA.setHostname("pupitre");
  ArduinoOTA.setPassword(reglages::OTA_MOT_DE_PASSE);  // pas celui du Wi-Fi : les joueurs le connaissent
  ArduinoOTA.begin();
  Serial.printf("Panneau : Wi-Fi « %s », http://%s\n", reglages::WIFI_NOM, WiFi.softAPIP().toString().c_str());
}

void maj() {
  serveur.handleClient();
  ArduinoOTA.handle();
}

}  // namespace panneau
