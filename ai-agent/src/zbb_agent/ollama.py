from __future__ import annotations

from typing import Any, Protocol

import httpx

from .config import Settings


class OllamaGateway(Protocol):
    async def health(self) -> dict[str, Any]: ...

    async def chat(self, payload: dict[str, Any]) -> dict[str, Any]: ...


class HttpOllamaGateway:
    def __init__(self, settings: Settings) -> None:
        self._base_url = settings.ollama_base_url
        self._timeout = settings.ollama_timeout_seconds

    async def health(self) -> dict[str, Any]:
        async with httpx.AsyncClient(timeout=3.0) as client:
            response = await client.get(f"{self._base_url}/api/version")
            response.raise_for_status()
            return response.json()

    async def chat(self, payload: dict[str, Any]) -> dict[str, Any]:
        async with httpx.AsyncClient(timeout=self._timeout) as client:
            response = await client.post(f"{self._base_url}/api/chat", json=payload)
            response.raise_for_status()
            return response.json()
