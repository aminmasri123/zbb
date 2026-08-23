from __future__ import annotations

import json
import time

import httpx
import pytest

from zbb_agent.auth import signature_for
from zbb_agent.config import Settings
from zbb_agent.main import create_app


SECRET = "api-test-secret-that-is-at-least-32-bytes"


class FakeOllama:
    async def health(self):
        return {"version": "0.test"}

    async def chat(self, payload):
        return {
            "message": {
                "tool_calls": [
                    {
                        "id": "call-api-1",
                        "function": {
                            "name": "get_participant_luv_data",
                            "arguments": {},
                        },
                    }
                ]
            }
        }


def app():
    return create_app(
        Settings(service_key_id="laravel", service_secret=SECRET),
        FakeOllama(),
    )


def signed_headers(path: str, body: bytes, nonce: str) -> dict[str, str]:
    timestamp = str(int(time.time()))
    return {
        "Content-Type": "application/json",
        "X-ZBB-Key-Id": "laravel",
        "X-ZBB-Timestamp": timestamp,
        "X-ZBB-Nonce": nonce,
        "X-ZBB-Signature": signature_for(
            SECRET, timestamp, nonce, "POST", path, body
        ),
    }


def request_body() -> bytes:
    return json.dumps(
        {
            "run_id": "11111111-1111-4111-8111-111111111111",
            "project_id": 17,
            "participant_id": 471,
            "report_type": "luv",
            "period": {"from_date": "2026-07-01", "until_date": "2026-07-31"},
            "user_request": "Erstelle einen LuV-Entwurf.",
            "allowed_tools": [
                "get_participant_luv_data",
                "get_project_report_rules",
            ],
            "tool_results": [],
        },
        ensure_ascii=False,
        separators=(",", ":"),
    ).encode()


@pytest.mark.asyncio
async def test_liveness_does_not_expose_service_details() -> None:
    async with httpx.AsyncClient(
        transport=httpx.ASGITransport(app=app()), base_url="http://test"
    ) as client:
        response = await client.get("/health/live")

    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


@pytest.mark.asyncio
async def test_agent_endpoint_requires_a_valid_signature_and_rejects_replay() -> None:
    body = request_body()
    headers = signed_headers("/v1/agent/turn", body, "api-nonce-1")
    async with httpx.AsyncClient(
        transport=httpx.ASGITransport(app=app()), base_url="http://test"
    ) as client:
        response = await client.post("/v1/agent/turn", content=body, headers=headers)

        assert response.status_code == 200
        assert response.json()["kind"] == "tool_calls"
        assert response.json()["calls"][0]["name"] == "get_participant_luv_data"

        replay = await client.post("/v1/agent/turn", content=body, headers=headers)
        assert replay.status_code == 401
        assert replay.json() == {"detail": "replayed request"}


@pytest.mark.asyncio
async def test_agent_endpoint_rejects_an_unsigned_request() -> None:
    async with httpx.AsyncClient(
        transport=httpx.ASGITransport(app=app()), base_url="http://test"
    ) as client:
        response = await client.post("/v1/agent/turn", content=request_body())

    assert response.status_code == 401
