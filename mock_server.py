import http.server, json

PORT = 8000

MOCK_DATA = {
    "projects": ["vCIO", "Shadow IT", "Network Admin", "Professional Services", "IT Project Manager"],
    "projectsMeta": {
        "vCIO": {"assigned": "admin, employee", "customer": "Thrive LLC", "duration": 12, "created_by": "admin"},
        "Shadow IT": {"assigned": "admin", "customer": "Shadow Corp", "duration": 6, "created_by": "admin"},
        "Network Admin": {"assigned": "admin, employee", "customer": "Netlink LLC", "duration": 12, "created_by": "admin"},
        "Professional Services": {"assigned": "admin", "customer": "Client Services", "duration": 1, "created_by": "admin"},
        "IT Project Manager": {"assigned": "admin", "customer": "Project Corp", "duration": 12, "created_by": "admin"}
    },
    "allotments": {
        "June": {"vCIO": 30.0, "Shadow IT": 40.0, "Network Admin": 50.0, "Professional Services": 0.0, "IT Project Manager": 0.0}
    },
    "entries": [
        {
            "id": "1",
            "project": "vCIO",
            "date": "2026-06-01",
            "checkIn": "09:00",
            "checkOut": "12:00",
            "staff": "John",
            "client": "Client A",
            "status": "Completed",
            "notes": "Work on vCIO"
        },
        {
            "id": "2",
            "project": "Network Admin",
            "date": "2026-06-02",
            "checkIn": "10:00",
            "checkOut": "14:00",
            "staff": "Jane",
            "client": "Client B",
            "status": "Completed",
            "notes": "Work on Network Admin"
        }
    ],
    "pending": [],
    "notifications": [
        {
            "id": "mock_notif_1",
            "type": "info",
            "title": "Welcome!",
            "msg": "Welcome to Claflin A.L.M. Interval time tracker.",
            "month": None,
            "read": False,
            "completed": False,
            "time": "Jun 12, 9:00 AM"
        }
    ]
}

MOCK_LOCKOUTS = [
    {
        "username": "locked_user",
        "ip_address": "192.168.1.100",
        "count": 6,
        "last_attempt": "2026-06-15 10:05:00",
        "type": "both"
    }
]

class MockHandler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path.startswith('/api/check_session.php'):
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            response = {"success": True, "user": {"username": "admin", "display": "Admin User", "role": "Admin"}}
            self.wfile.write(json.dumps(response).encode())
        elif self.path.startswith('/api/get_entries.php'):
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            self.wfile.write(json.dumps(MOCK_DATA).encode())
        elif self.path.startswith('/api/admin_lockouts.php'):
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            response = {
                "success": True,
                "lockouts": MOCK_LOCKOUTS
            }
            self.wfile.write(json.dumps(response).encode())
        else:
            super().do_GET()

    def do_POST(self):
        content_length = int(self.headers.get('Content-Length', 0))
        post_data = self.rfile.read(content_length) if content_length > 0 else b'{}'
        try:
            data = json.loads(post_data.decode('utf-8'))
        except:
            data = {}

        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()

        if self.path.startswith('/api/login.php'):
            response = {"success": True, "user": {"username": "admin", "display": "Admin User", "role": "Admin"}}
            self.wfile.write(json.dumps(response).encode())

        elif self.path.startswith('/api/admin_lockouts.php'):
            type_val = data.get('type')
            target_val = data.get('target')
            global MOCK_LOCKOUTS
            if type_val == 'both':
                parts = target_val.split('|')
                u_target = parts[0] if len(parts) > 0 else ''
                ip_target = parts[1] if len(parts) > 1 else ''
                MOCK_LOCKOUTS = [
                    l for l in MOCK_LOCKOUTS
                    if (not u_target or l.get("username") != u_target) and (not ip_target or l.get("ip_address") != ip_target)
                ]
            elif type_val == 'username':
                MOCK_LOCKOUTS = [l for l in MOCK_LOCKOUTS if l.get("username") != target_val]
            elif type_val == 'ip':
                MOCK_LOCKOUTS = [l for l in MOCK_LOCKOUTS if l.get("ip_address") != target_val]
            response = {"success": True, "message": "Lockout reset successfully."}
            self.wfile.write(json.dumps(response).encode())

        elif self.path.startswith('/api/save_entry.php'):
            action = data.get('action')
            entry_id = str(data.get('id'))
            
            entry = {
                "id": entry_id,
                "project": data.get('project', ''),
                "date": data.get('date', ''),
                "checkIn": data.get('checkIn', ''),
                "checkOut": data.get('checkOut', ''),
                "staff": data.get('staff', ''),
                "client": data.get('client', ''),
                "status": data.get('status', ''),
                "notes": data.get('notes', '')
            }
            
            if action == 'update':
                updated = False
                for i, e in enumerate(MOCK_DATA['entries']):
                    if str(e['id']) == entry_id:
                        MOCK_DATA['entries'][i] = entry
                        updated = True
                        break
                if not updated:
                    MOCK_DATA['entries'].append(entry)
            else:
                MOCK_DATA['entries'].append(entry)
                
            response = {"success": True, "status": "success", "id": entry_id}
            self.wfile.write(json.dumps(response).encode())

        elif self.path.startswith('/api/update_entry.php'):
            action = data.get('action')
            entry_id = str(data.get('id'))
            if action == 'delete':
                MOCK_DATA['entries'] = [e for e in MOCK_DATA['entries'] if str(e['id']) != entry_id]
                response = {"status": "success", "affected": 1}
            elif action == 'approve':
                response = {"status": "success"}
            elif action == 'reject':
                response = {"status": "success"}
            else:
                response = {"status": "success"}
            self.wfile.write(json.dumps(response).encode())

        elif self.path.startswith('/api/create_project.php'):
            projectName = data.get('projectName', '').strip()
            customer = data.get('customer', '').strip()
            duration = int(data.get('duration', 1))
            allotment = float(data.get('allotment', 0.00))
            assigned = data.get('assigned', '').strip()
            startMonth = data.get('startMonth', '').strip()
            
            assigned_list = [a.strip() for a in assigned.split(',') if a.strip()]
            if 'admin' not in assigned_list:
                assigned_list.append('admin')
            assigned = ', '.join(assigned_list)
            
            if projectName and projectName not in MOCK_DATA['projects']:
                MOCK_DATA['projects'].append(projectName)
                MOCK_DATA['projectsMeta'][projectName] = {
                    "assigned": assigned,
                    "customer": customer,
                    "duration": duration,
                    "created_by": "admin"
                }
                if startMonth:
                    if startMonth not in MOCK_DATA['allotments']:
                        MOCK_DATA['allotments'][startMonth] = {}
                    MOCK_DATA['allotments'][startMonth][projectName] = allotment
                
            response = {"success": True, "message": "Project created successfully."}
            self.wfile.write(json.dumps(response).encode())

        elif self.path.startswith('/api/save_allotment.php'):
            month = data.get('month', '')
            allotments = data.get('allotments', {})
            if month:
                if month not in MOCK_DATA['allotments']:
                    MOCK_DATA['allotments'][month] = {}
                for p, val in allotments.items():
                    MOCK_DATA['allotments'][month][p] = float(val)
            response = {"success": True, "message": "Allotments saved successfully."}
            self.wfile.write(json.dumps(response).encode())
            
        elif self.path.startswith('/api/save_notification.php'):
            notif = {
                "id": data.get("id"),
                "type": data.get("type", "info"),
                "title": data.get("title", ""),
                "msg": data.get("msg", ""),
                "month": data.get("month"),
                "read": data.get("read", False),
                "completed": data.get("completed", False),
                "time": data.get("time", "")
            }
            if "notifications" not in MOCK_DATA:
                MOCK_DATA["notifications"] = []
            MOCK_DATA["notifications"].insert(0, notif)
            response = {"success": True}
            self.wfile.write(json.dumps(response).encode())

        elif self.path.startswith('/api/update_notification.php'):
            notif_id = data.get("id")
            action = data.get("action")
            if "notifications" in MOCK_DATA:
                for n in MOCK_DATA["notifications"]:
                    if n["id"] == notif_id:
                        if action == "read":
                            n["read"] = True
                        elif action == "complete":
                            n["read"] = True
                            n["completed"] = True
            response = {"success": True}
            self.wfile.write(json.dumps(response).encode())
            
        else:
            response = {"success": True, "status": "success"}
            self.wfile.write(json.dumps(response).encode())

if __name__ == '__main__':
    print(f"Mock server running at http://localhost:{PORT}")
    srv = http.server.HTTPServer(('localhost', PORT), MockHandler)
    srv.serve_forever()
