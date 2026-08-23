from __future__ import annotations

from fastapi import Depends, FastAPI, HTTPException, status
import httpx

from .auth import RequestAuthenticator
from .config import Settings
from .ollama import HttpOllamaGateway, OllamaGateway
from .schemas import AgentTurnRequest, AgentTurnResponse, WorkspaceGenerateRequest, WorkspaceGenerateResponse
from .service import AgentService


def create_app(
    settings: Settings | None = None,
    ollama: OllamaGateway | None = None,
) -> FastAPI:
    active_settings = settings or Settings.from_environment()
    active_settings.validate()
    active_ollama = ollama or HttpOllamaGateway(active_settings)
    authenticator = RequestAuthenticator(active_settings)
    agent = AgentService(active_settings, active_ollama)

    app = FastAPI(
        title="ZBB Internal AI Agent",
        version="0.2.1",
        docs_url=None,
        redoc_url=None,
        openapi_url=None,
    )

    @app.get("/health/live")
    async def live() -> dict[str, str]:
        return {"status": "ok"}

    @app.get("/health/ready", dependencies=[Depends(authenticator)])
    async def ready() -> dict[str, str]:
        try:
            ollama_status = await active_ollama.health()
        except (httpx.HTTPError, ValueError) as exception:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Ollama unavailable",
            ) from exception
        return {"status": "ok", "ollama_version": str(ollama_status.get("version", "unknown"))}

    @app.post(
        "/v1/agent/turn",
        response_model=AgentTurnResponse,
        dependencies=[Depends(authenticator)],
    )
    async def next_turn(request: AgentTurnRequest) -> AgentTurnResponse:
        return await agent.next_turn(request)

    @app.post("/v1/workspace/generate", response_model=WorkspaceGenerateResponse, dependencies=[Depends(authenticator)])
    async def workspace_generate(request: WorkspaceGenerateRequest) -> WorkspaceGenerateResponse:
        return await agent.generate(request)

    return app


def application() -> FastAPI:
    return create_app()
