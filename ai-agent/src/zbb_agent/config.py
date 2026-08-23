from __future__ import annotations

from dataclasses import dataclass
import os
from urllib.parse import urlparse


@dataclass(frozen=True, slots=True)
class Settings:
    service_key_id: str
    service_secret: str
    ollama_base_url: str = "http://127.0.0.1:11434"
    ollama_model: str = "qwen3:1.7b"
    ollama_vision_model: str = "qwen3-vl:2b-instruct"
    request_max_bytes: int = 16_000_000
    signature_max_age_seconds: int = 60
    ollama_timeout_seconds: float = 240.0

    @classmethod
    def from_environment(cls) -> "Settings":
        return cls(
            service_key_id=os.environ.get("ZBB_AGENT_KEY_ID", "").strip(),
            service_secret=os.environ.get("ZBB_AGENT_SECRET", ""),
            ollama_base_url=os.environ.get(
                "ZBB_OLLAMA_BASE_URL", "http://127.0.0.1:11434"
            ).rstrip("/"),
            ollama_model=os.environ.get("ZBB_OLLAMA_MODEL", "qwen3:1.7b").strip(),
            ollama_vision_model=os.environ.get("ZBB_OLLAMA_VISION_MODEL", "qwen3-vl:2b-instruct").strip(),
            request_max_bytes=int(os.environ.get("ZBB_AGENT_REQUEST_MAX_BYTES", "16000000")),
            signature_max_age_seconds=int(
                os.environ.get("ZBB_AGENT_SIGNATURE_MAX_AGE_SECONDS", "60")
            ),
            ollama_timeout_seconds=float(
                os.environ.get("ZBB_OLLAMA_TIMEOUT_SECONDS", "240")
            ),
        )

    def validate(self) -> None:
        if not self.service_key_id:
            raise ValueError("ZBB_AGENT_KEY_ID is required")
        if len(self.service_secret.encode("utf-8")) < 32:
            raise ValueError("ZBB_AGENT_SECRET must contain at least 32 bytes")
        if not self.ollama_model:
            raise ValueError("ZBB_OLLAMA_MODEL is required")
        if not self.ollama_vision_model:
            raise ValueError("ZBB_OLLAMA_VISION_MODEL is required")
        if self.request_max_bytes < 1_024:
            raise ValueError("ZBB_AGENT_REQUEST_MAX_BYTES is too small")
        if not 10 <= self.signature_max_age_seconds <= 300:
            raise ValueError("signature max age must be between 10 and 300 seconds")

        parsed = urlparse(self.ollama_base_url)
        if parsed.scheme != "http" or parsed.hostname not in {"127.0.0.1", "localhost", "::1"}:
            raise ValueError("Ollama must use an HTTP loopback URL")
