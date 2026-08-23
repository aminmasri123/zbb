#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import hmac
import json
from pathlib import Path
import time
from urllib.error import URLError
from urllib.request import Request, urlopen
import uuid


ENV_FILE = Path("/etc/zbb-ai-agent/agent.env")
BASE_URL = "http://127.0.0.1:8000"


def load_environment() -> dict[str, str]:
    values: dict[str, str] = {}
    for raw_line in ENV_FILE.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        key, separator, value = line.partition("=")
        if not separator:
            raise RuntimeError(f"Ungültige Konfigurationszeile für {key!r}")
        values[key] = value
    return values


def signed_request(
    environment: dict[str, str], method: str, path: str, body: bytes = b""
) -> tuple[int, dict[str, object]]:
    timestamp = str(int(time.time()))
    nonce = str(uuid.uuid4())
    body_digest = hashlib.sha256(body).hexdigest()
    canonical = f"{timestamp}\n{nonce}\n{method}\n{path}\n{body_digest}".encode()
    signature = hmac.new(
        environment["ZBB_AGENT_SECRET"].encode(), canonical, hashlib.sha256
    ).hexdigest()
    headers = {
        "X-ZBB-Key-Id": environment["ZBB_AGENT_KEY_ID"],
        "X-ZBB-Timestamp": timestamp,
        "X-ZBB-Nonce": nonce,
        "X-ZBB-Signature": signature,
    }
    if body:
        headers["Content-Type"] = "application/json"
    request = Request(
        f"{BASE_URL}{path}",
        data=body if method == "POST" else None,
        headers=headers,
        method=method,
    )
    with urlopen(request, timeout=130) as response:
        return response.status, json.loads(response.read())


def main() -> None:
    environment = load_environment()
    if len(environment.get("ZBB_AGENT_SECRET", "")) < 32:
        raise RuntimeError("Service-Secret fehlt oder ist zu kurz")

    ready_status = 0
    ready: dict[str, object] = {}
    for attempt in range(1, 6):
        try:
            ready_status, ready = signed_request(environment, "GET", "/health/ready")
            break
        except URLError:
            if attempt == 5:
                raise
            time.sleep(1)
    if ready_status != 200 or ready.get("status") != "ok":
        raise RuntimeError(f"Readiness fehlgeschlagen: {ready_status} {ready}")
    print(f"readiness=ok ollama_version={ready.get('ollama_version', 'unknown')}")

    request_body = json.dumps(
        {
            "run_id": "22222222-2222-4222-8222-222222222222",
            "project_id": 17,
            "participant_id": 471,
            "report_type": "luv",
            "period": {"from_date": "2026-07-01", "until_date": "2026-07-31"},
            "user_request": (
                "Synthetischer Infrastrukturtest: Fordere zuerst die freigegebenen "
                "Projektregeln an und erfinde keine Fakten."
            ),
            "allowed_tools": ["get_project_report_rules"],
            "tool_results": [],
        },
        ensure_ascii=False,
        separators=(",", ":"),
    ).encode()
    turn_status, turn = signed_request(
        environment, "POST", "/v1/agent/turn", request_body
    )
    if turn_status != 200:
        raise RuntimeError(f"Agent-Turn fehlgeschlagen: {turn_status}")
    if turn.get("kind") != "tool_calls":
        raise RuntimeError(f"Unerwartete Agent-Antwort: {turn.get('kind')}")

    calls = turn.get("calls")
    if not isinstance(calls, list) or len(calls) != 1:
        raise RuntimeError("Es wurde nicht genau ein Tool Call erzeugt")
    call = calls[0]
    if call.get("name") != "get_project_report_rules" or call.get("arguments") != {}:
        raise RuntimeError("Der Tool Call verletzt Name- oder Argument-Allowlist")
    print("agent_turn=ok tool=get_project_report_rules arguments={}")
    workspace_body = json.dumps(
        {
            "run_id": "33333333-3333-4333-8333-333333333333",
            "task": "chat",
            "instruction": "Antworte nur mit einem kurzen deutschen Testsatz.",
            "sources": [],
            "image_base64": None,
        },
        ensure_ascii=False,
        separators=(",", ":"),
    ).encode()
    workspace_status, workspace = signed_request(
        environment, "POST", "/v1/workspace/generate", workspace_body
    )
    if workspace_status != 200 or workspace.get("task") != "chat":
        raise RuntimeError(f"KI-Arbeitsbereich fehlgeschlagen: {workspace_status}")
    if not isinstance(workspace.get("content"), str) or not workspace["content"].strip():
        raise RuntimeError("KI-Arbeitsbereich lieferte keinen Text")
    print("workspace_chat=ok")
    print("secret_exposed=false")


if __name__ == "__main__":
    main()
