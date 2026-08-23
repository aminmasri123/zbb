from __future__ import annotations

import time

import pytest
from starlette.requests import Request

from zbb_agent.auth import RequestAuthenticator, signature_for
from zbb_agent.config import Settings


SECRET = "test-secret-that-is-at-least-32-bytes-long"


def settings() -> Settings:
    return Settings(service_key_id="laravel", service_secret=SECRET)


def request_for(body: bytes, timestamp: str, nonce: str, signature: str) -> Request:
    headers = [
        (b"x-zbb-key-id", b"laravel"),
        (b"x-zbb-timestamp", timestamp.encode()),
        (b"x-zbb-nonce", nonce.encode()),
        (b"x-zbb-signature", signature.encode()),
    ]
    delivered = False

    async def receive():
        nonlocal delivered
        if delivered:
            return {"type": "http.request", "body": b"", "more_body": False}
        delivered = True
        return {"type": "http.request", "body": body, "more_body": False}

    return Request(
        {
            "type": "http",
            "method": "POST",
            "path": "/v1/agent/turn",
            "headers": headers,
            "query_string": b"",
            "server": ("test", 80),
            "client": ("127.0.0.1", 1234),
            "scheme": "http",
        },
        receive,
    )


@pytest.mark.asyncio
async def test_valid_signature_is_accepted_once() -> None:
    timestamp = str(int(time.time()))
    body = b'{"safe":true}'
    signature = signature_for(
        SECRET, timestamp, "nonce-1", "POST", "/v1/agent/turn", body
    )
    authenticator = RequestAuthenticator(settings())

    await authenticator(request_for(body, timestamp, "nonce-1", signature))

    with pytest.raises(Exception) as replay:
        await authenticator(request_for(body, timestamp, "nonce-1", signature))
    assert replay.value.status_code == 401


@pytest.mark.asyncio
async def test_modified_body_is_rejected() -> None:
    timestamp = str(int(time.time()))
    signature = signature_for(
        SECRET, timestamp, "nonce-2", "POST", "/v1/agent/turn", b"original"
    )

    with pytest.raises(Exception) as rejected:
        await RequestAuthenticator(settings())(
            request_for(b"modified", timestamp, "nonce-2", signature)
        )
    assert rejected.value.status_code == 401
