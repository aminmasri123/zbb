from __future__ import annotations

from datetime import date
from uuid import UUID

import pytest

from zbb_agent.config import Settings
from zbb_agent.schemas import (
    AgentTurnRequest,
    DatePeriod,
    ReportType,
    ToolName,
    ToolResultMessage,
    WorkspaceGenerateRequest,
    WorkspaceTask,
)
from zbb_agent.service import AgentService


RUN_ID = UUID("11111111-1111-4111-8111-111111111111")


class FakeOllama:
    def __init__(self, response):
        self.response = response
        self.payload = None

    async def health(self):
        return {"version": "test"}

    async def chat(self, payload):
        self.payload = payload
        return self.response


def request(**changes) -> AgentTurnRequest:
    values = {
        "run_id": RUN_ID,
        "project_id": 17,
        "participant_id": 471,
        "report_type": ReportType.LUV,
        "period": DatePeriod(from_date=date(2026, 7, 1), until_date=date(2026, 7, 31)),
        "user_request": "Erstelle einen LuV-Entwurf.",
        "allowed_tools": {ToolName.LUV_DATA, ToolName.PROJECT_RULES},
        "tool_results": [],
    }
    values.update(changes)
    return AgentTurnRequest(**values)


def service(response) -> AgentService:
    return AgentService(
        Settings(service_key_id="laravel", service_secret="x" * 32),
        FakeOllama(response),
    )


@pytest.mark.asyncio
async def test_workspace_blocks_unsourced_political_fact_questions() -> None:
    fake = FakeOllama({"message": {"content": "must not be used"}})
    agent = AgentService(
        Settings(service_key_id="laravel", service_secret="x" * 32), fake
    )
    workspace_request = WorkspaceGenerateRequest(
        run_id=RUN_ID,
        task=WorkspaceTask.CHAT,
        instruction="Wer ist der Bundeskanzler und welche Aufgaben hat er?",
        sources=[],
    )

    result = await agent.generate(workspace_request)

    assert result.title == "Verlässliche Quelle erforderlich"
    assert result.citations == []
    assert result.warnings
    assert fake.payload is None


@pytest.mark.asyncio
async def test_workspace_blocks_unsourced_person_identity_questions() -> None:
    fake = FakeOllama({"message": {"content": "must not be used"}})
    agent = AgentService(Settings(service_key_id="laravel", service_secret="x" * 32), fake)
    result = await agent.generate(WorkspaceGenerateRequest(
        run_id=RUN_ID,
        task=WorkspaceTask.CHAT,
        instruction="Wer ist Lionel Messi?",
        sources=[],
    ))

    assert result.title == "Verlässliche Quelle erforderlich"
    assert fake.payload is None


@pytest.mark.asyncio
async def test_workspace_normalizes_model_metadata_and_unknown_citations() -> None:
    fake = FakeOllama({"message": {"content": """{
        "run_id":"99999999-9999-4999-8999-999999999999",
        "task":"compare",
        "title":"Entwurf",
        "content":"Sicherer Text",
        "citations":["bad",{"source_id":"unknown","page":1}],
        "warnings":"not-a-list",
        "unexpected":true
    }"""}})
    agent = AgentService(Settings(service_key_id="laravel", service_secret="x" * 32), fake)
    result = await agent.generate(WorkspaceGenerateRequest(
        run_id=RUN_ID,
        task=WorkspaceTask.CHAT,
        instruction="Formuliere einen freundlichen Begrüßungssatz.",
        sources=[],
    ))

    assert result.run_id == RUN_ID
    assert result.task == WorkspaceTask.CHAT
    assert result.content == "Sicherer Text"
    assert result.citations == []
    assert result.warnings == []


@pytest.mark.asyncio
async def test_allowed_argument_free_tool_call_is_returned() -> None:
    fake = FakeOllama(
        {
            "message": {
                "tool_calls": [
                    {
                        "id": "call-1",
                        "function": {
                            "name": "get_participant_luv_data",
                            "arguments": {},
                        },
                    }
                ]
            }
        }
    )
    result = await AgentService(
        Settings(service_key_id="laravel", service_secret="x" * 32), fake
    ).next_turn(request())

    assert result.kind == "tool_calls"
    assert result.calls[0].name == ToolName.LUV_DATA
    assert "format" not in fake.payload


@pytest.mark.asyncio
async def test_model_cannot_supply_participant_or_project_arguments() -> None:
    with pytest.raises(Exception) as rejected:
        await service(
            {
                "message": {
                    "tool_calls": [
                        {
                            "function": {
                                "name": "get_participant_luv_data",
                                "arguments": {"participant_id": 999},
                            }
                        }
                    ]
                }
            }
        ).next_turn(request())
    assert rejected.value.status_code == 502


@pytest.mark.asyncio
async def test_model_cannot_call_tool_outside_allowlist() -> None:
    with pytest.raises(Exception) as rejected:
        await service(
            {
                "message": {
                    "tool_calls": [
                        {
                            "function": {
                                "name": "get_attendance_summary",
                                "arguments": {},
                            }
                        }
                    ]
                }
            }
        ).next_turn(request())
    assert rejected.value.status_code == 502


@pytest.mark.asyncio
async def test_final_report_rejects_unknown_sources() -> None:
    response = {
        "message": {
            "content": """{
                "report_type":"luv",
                "title":"LuV-Entwurf",
                "sections":[{
                    "heading":"Verlauf",
                    "claims":[{
                        "claim_id":"c1",
                        "text":"Nicht belegte Aussage",
                        "status":"supported",
                        "source_ids":["note-999"]
                    }]
                }],
                "warnings":[]
            }"""
        }
    }
    tool_results = [
        ToolResultMessage(
            tool_name=ToolName.LUV_DATA,
            content={"items": [{"source_id": "note-1", "text": "Dokumentiert"}]},
        )
    ]

    with pytest.raises(Exception) as rejected:
        await service(response).next_turn(request(tool_results=tool_results))
    assert rejected.value.status_code == 502


@pytest.mark.asyncio
async def test_final_report_accepts_known_sources_and_missing_data_marker() -> None:
    response = {
        "message": {
            "content": """{
                "report_type":"luv",
                "title":"LuV-Entwurf",
                "sections":[{
                    "heading":"Verlauf",
                    "claims":[
                        {
                            "claim_id":"c1",
                            "text":"Dokumentierte Aussage",
                            "status":"supported",
                            "source_ids":["note-1"]
                        },
                        {
                            "claim_id":"c2",
                            "text":"Keine ausreichenden Informationen vorhanden.",
                            "status":"insufficient_data",
                            "source_ids":[]
                        }
                    ]
                }],
                "warnings":[]
            }"""
        }
    }
    tool_results = [
        ToolResultMessage(
            tool_name=ToolName.LUV_DATA,
            content={"items": [{"source_id": "note-1", "text": "Dokumentiert"}]},
        )
    ]

    result = await service(response).next_turn(request(tool_results=tool_results))

    assert result.kind == "final"
    assert result.report.sections[0].claims[0].source_ids == ["note-1"]


@pytest.mark.asyncio
async def test_evidence_turn_uses_fast_report_model_and_bounded_json_output() -> None:
    fake = FakeOllama({
        "message": {
            "content": """{
                "report_type":"luv",
                "title":"Kurzer Entwurf",
                "sections":[{
                    "heading":"Datenlage",
                    "claims":[{
                        "claim_id":"c1",
                        "text":"Dokumentierte Aussage",
                        "status":"supported",
                        "source_ids":["note-1"]
                    }]
                }],
                "warnings":[]
            }"""
        }
    })
    settings = Settings(
        service_key_id="laravel",
        service_secret="x" * 32,
        ollama_model="qwen3:4b-instruct-2507-q4_K_M",
        ollama_report_model="qwen3:1.7b",
    )
    tool_results = [
        ToolResultMessage(
            tool_name=ToolName.LUV_DATA,
            content={"items": [{"source_id": "note-1", "text": "Dokumentiert"}]},
        )
    ]

    await AgentService(settings, fake).next_turn(request(tool_results=tool_results))

    assert fake.payload["model"] == "qwen3:1.7b"
    assert fake.payload["format"] == "json"
    assert "tools" not in fake.payload
    assert fake.payload["options"]["num_predict"] == 900
