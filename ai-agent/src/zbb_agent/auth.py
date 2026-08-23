from __future__ import annotations

import asyncio
from dataclasses import dataclass, field
import hashlib
import hmac
import time

from fastapi import HTTPException, Request, status

from .config import Settings


@dataclass(slots=True)
class NonceStore:
    _expires_at: dict[str, int] = field(default_factory=dict)
    _lock: asyncio.Lock = field(default_factory=asyncio.Lock)

    async def consume(self, nonce: str, now: int, ttl_seconds: int) -> bool:
        async with self._lock:
            self._expires_at = {
                value: expiry for value, expiry in self._expires_at.items() if expiry >= now
            }
            if nonce in self._expires_at:
                return False
            self._expires_at[nonce] = now + ttl_seconds
            return True


def canonical_request(timestamp: str, nonce: str, method: str, path: str, body: bytes) -> bytes:
    body_digest = hashlib.sha256(body).hexdigest()
    return f"{timestamp}\n{nonce}\n{method.upper()}\n{path}\n{body_digest}".encode()


def signature_for(
    secret: str, timestamp: str, nonce: str, method: str, path: str, body: bytes
) -> str:
    return hmac.new(
        secret.encode(),
        canonical_request(timestamp, nonce, method, path, body),
        hashlib.sha256,
    ).hexdigest()


class RequestAuthenticator:
    def __init__(self, settings: Settings, nonce_store: NonceStore | None = None) -> None:
        self.settings = settings
        self.nonce_store = nonce_store or NonceStore()

    async def __call__(self, request: Request) -> None:
        key_id = request.headers.get("X-ZBB-Key-Id", "")
        timestamp_value = request.headers.get("X-ZBB-Timestamp", "")
        nonce = request.headers.get("X-ZBB-Nonce", "")
        supplied_signature = request.headers.get("X-ZBB-Signature", "")

        if key_id != self.settings.service_key_id:
            self._reject("unknown service key")
        if not nonce or len(nonce) > 128:
            self._reject("invalid nonce")
        if len(supplied_signature) != 64:
            self._reject("invalid signature")

        try:
            timestamp = int(timestamp_value)
        except ValueError:
            self._reject("invalid timestamp")

        now = int(time.time())
        if abs(now - timestamp) > self.settings.signature_max_age_seconds:
            self._reject("expired request")

        body = await request.body()
        if len(body) > self.settings.request_max_bytes:
            raise HTTPException(
                status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
                detail="request body too large",
            )

        expected = signature_for(
            self.settings.service_secret,
            timestamp_value,
            nonce,
            request.method,
            request.url.path,
            body,
        )
        if not hmac.compare_digest(expected, supplied_signature.lower()):
            self._reject("invalid signature")

        if not await self.nonce_store.consume(
            nonce, now, self.settings.signature_max_age_seconds
        ):
            self._reject("replayed request")

    @staticmethod
    def _reject(detail: str) -> None:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail=detail)
