# Faux serveur Messages API pour le tournage de la vidéo des nouveautés 2.13 :
# réponses scriptées (question sur le dossier, plan de séance, ordonnance, synthèse vétérinaire).
# Le traitement côté Jack&Cie (affichage, droits, enregistrement) reste réel.
import json, datetime
from http.server import BaseHTTPRequestHandler, HTTPServer

def reply(content, stop='end_turn'):
    return {"id": "msg_demo", "type": "message", "role": "assistant", "model": "claude-opus-5-5", "content": content,
            "stop_reason": stop, "stop_sequence": None, "usage": {"input_tokens": 1200, "output_tokens": 120}}

PLAN = {"summary": "Rectitude en 45 minutes : échauffement souple, lignes droites et transitions, retour au calme.",
        "warmup": [{"name": "Allongement du pas", "minutes": 8, "note": "Pas actif, rênes longues"},
                   {"name": "Diagonales au trot en cadence", "minutes": 7, "note": "Sur les deux mains"}],
        "main": [{"name": "Arrêt droit après la réception", "minutes": 10, "note": "Garder les épaules devant"},
                 {"name": "Arrêts et départs attelés", "minutes": 10, "note": "Transitions nettes et droites"}],
        "complementary": [], "cooldown": [{"name": "Balade d'habituation", "minutes": 10, "note": "Pas détendu"}]}
RX = {"is_prescription": True, "practitioner": "Dr Anne Leroy", "visit_date": (datetime.date.today() - datetime.timedelta(days=1)).isoformat(),
      "care_category": "veterinary", "horse_name": "Uni", "reason": "Boiterie antérieur gauche", "diagnosis": "Abcès de pied",
      "instructions": "Bain de pied tiède 2 fois par jour pendant 5 jours, repos au box", "next_check_on": "", "next_check_days": 10, "confidence": "high",
      "treatments": [{"product": "Equipalazone", "dosage": "1 sachet matin et soir", "frequency": "matin et soir", "times_per_day": 2, "duration_days": 5, "route": "orale, dans la ration"}]}

class H(BaseHTTPRequestHandler):
    def do_POST(self):
        raw = self.rfile.read(int(self.headers['Content-Length'])).decode()
        body = json.loads(raw)
        if '"warmup"' in raw:
            out = reply([{"type": "text", "text": json.dumps(PLAN, ensure_ascii=False)}])
        elif 'is_prescription' in raw:
            out = reply([{"type": "text", "text": json.dumps(RX, ensure_ascii=False)}])
        elif 'vétérinaire équin' in raw or 'v\\u00e9t\\u00e9rinaire \\u00e9quin' in raw:
            out = reply([{"type": "text", "text": "Uni, hongre de 18 ans, a présenté une boiterie de l'antérieur gauche ; un abcès de pied a été diagnostiqué et traité. Le maréchal est passé fin septembre. Poids stable autour de 520 kg. Aucune anomalie signalée en séance ces dernières semaines."}])
        elif 'récapitulatif hebdomadaire' in raw or 'r\\u00e9capitulatif hebdomadaire' in raw:
            out = reply([{"type": "text", "text": "Belle semaine en vue pour Uni : séance de dressage mercredi, et pensez au bilan après."}])
        else:
            out = reply([{"type": "text", "text": "Cette année, le maréchal vous a coûté 380 € pour Uni, en 4 passages. Le prochain est prévu vers le 20 novembre."}])
        data = json.dumps(out).encode()
        self.send_response(200); self.send_header('Content-Type', 'application/json'); self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data)
    def log_message(self, *a): pass
HTTPServer(('127.0.0.1', 8098), H).serve_forever()
