# Faux serveur Messages API pour le tournage : réponse scriptée de l'assistant
# (le traitement côté Jack&Cie — création de la séance, calendrier, annulation — est réel).
import json, datetime
from http.server import BaseHTTPRequestHandler, HTTPServer

HORSE_ID = 1
class H(BaseHTTPRequestHandler):
    def do_POST(self):
        body = json.loads(self.rfile.read(int(self.headers['Content-Length'])))
        last = body['messages'][-1]
        has_result = isinstance(last.get('content'), list) and any(b.get('type') == 'tool_result' for b in last['content'])
        tomorrow = (datetime.date.today() + datetime.timedelta(days=1)).isoformat()
        if not has_result:
            content = [{"type": "tool_use", "id": "toolu_demo1", "name": "create_session", "input": {
                "horse_id": HORSE_ID, "scheduled_at": tomorrow + " 16:00", "session_type": "group_lesson",
                "instructor": "Jimmy", "planned_minutes": 60, "exercises": []}}]
            stop = "tool_use"
        else:
            content = [{"type": "text", "text": "C'est noté : cours collectif avec Jimmy pour Uni demain à 16 h. Je vous proposerai d'en faire le bilan après."}]
            stop = "end_turn"
        out = {"id": "msg_demo", "type": "message", "role": "assistant", "model": body.get('model'), "content": content,
               "stop_reason": stop, "stop_sequence": None, "usage": {"input_tokens": 1200, "output_tokens": 80}}
        data = json.dumps(out).encode()
        self.send_response(200); self.send_header('Content-Type', 'application/json'); self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data)
    def log_message(self, *a): pass
HTTPServer(('127.0.0.1', 8098), H).serve_forever()
