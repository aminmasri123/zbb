#!/usr/bin/env python3
import base64, json, time
from pathlib import Path
from urllib.request import Request, urlopen

image=base64.b64encode(Path('/home/aminmasri/zbb-app-icon-192.png').read_bytes()).decode()
payload=json.dumps({'model':'qwen3-vl:2b-instruct','stream':False,'think':False,'format':'json','messages':[{'role':'user','content':'Beschreibe dieses Bild kurz auf Deutsch. Antworte als JSON mit dem Feld description.','images':[image]}],'options':{'temperature':0,'num_ctx':2048,'num_predict':300}}).encode()
started=time.monotonic()
with urlopen(Request('http://127.0.0.1:11434/api/chat',data=payload,headers={'Content-Type':'application/json'}),timeout=130) as response:
    result=json.loads(response.read())
content=result.get('message',{}).get('content','')
if not content: raise RuntimeError('Visionmodell lieferte keinen Inhalt')
print(f"vision_model=ok elapsed_seconds={time.monotonic()-started:.1f} content_chars={len(content)}")
