from __future__ import annotations

from datetime import date
from enum import StrEnum
from typing import Annotated, Any, Literal
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field, model_validator


class StrictModel(BaseModel):
    model_config = ConfigDict(extra="forbid")


class ReportType(StrEnum):
    LUV = "luv"
    INTERIM = "interim"
    FINAL = "final"


class ToolName(StrEnum):
    PARTICIPANT_IDENTITY = "get_participant_identity_summary"
    LUV_DATA = "get_participant_luv_data"
    ATTENDANCE = "get_attendance_summary"
    DOCUMENTATION = "get_documentation_entries"
    GOALS = "get_goals_and_progress"
    PROJECT_RULES = "get_project_report_rules"
    TEMPLATE = "get_report_template_metadata"


class DatePeriod(StrictModel):
    from_date: date
    until_date: date

    @model_validator(mode="after")
    def validate_order(self) -> "DatePeriod":
        if self.until_date < self.from_date:
            raise ValueError("until_date must not precede from_date")
        return self


class ToolResultMessage(StrictModel):
    role: Literal["tool"] = "tool"
    tool_name: ToolName
    content: dict[str, Any]


class AgentTurnRequest(StrictModel):
    run_id: UUID
    project_id: Annotated[int, Field(gt=0)]
    participant_id: Annotated[int, Field(gt=0)]
    report_type: ReportType
    period: DatePeriod
    user_request: Annotated[str, Field(min_length=1, max_length=4_000)]
    allowed_tools: Annotated[set[ToolName], Field(min_length=1)]
    tool_results: Annotated[list[ToolResultMessage], Field(max_length=30)] = Field(
        default_factory=list
    )

    @model_validator(mode="after")
    def validate_tool_results(self) -> "AgentTurnRequest":
        unexpected = {
            result.tool_name for result in self.tool_results
        } - self.allowed_tools
        if unexpected:
            raise ValueError("tool results must belong to the run allowlist")
        return self


class ToolCall(StrictModel):
    call_id: Annotated[str, Field(min_length=1, max_length=200)]
    name: ToolName
    arguments: dict[str, Any] = Field(default_factory=dict)


class ClaimStatus(StrEnum):
    SUPPORTED = "supported"
    INSUFFICIENT_DATA = "insufficient_data"


class ReportClaim(StrictModel):
    claim_id: Annotated[str, Field(pattern=r"^[a-zA-Z0-9_-]{1,80}$")]
    text: Annotated[str, Field(min_length=1, max_length=4_000)]
    status: ClaimStatus
    source_ids: Annotated[
        list[Annotated[str, Field(min_length=1, max_length=200)]],
        Field(max_length=50),
    ] = Field(default_factory=list)

    @model_validator(mode="after")
    def validate_sources(self) -> "ReportClaim":
        if self.status == ClaimStatus.SUPPORTED and not self.source_ids:
            raise ValueError("supported claims require at least one source")
        if self.status == ClaimStatus.INSUFFICIENT_DATA and self.source_ids:
            raise ValueError("insufficient-data claims cannot cite sources")
        return self


class ReportSection(StrictModel):
    heading: Annotated[str, Field(min_length=1, max_length=200)]
    claims: Annotated[list[ReportClaim], Field(max_length=100)]


class DraftReport(StrictModel):
    report_type: ReportType
    title: Annotated[str, Field(min_length=1, max_length=300)]
    sections: Annotated[list[ReportSection], Field(min_length=1, max_length=30)]
    warnings: Annotated[list[str], Field(max_length=50)] = Field(default_factory=list)


class ToolCallsResponse(StrictModel):
    kind: Literal["tool_calls"] = "tool_calls"
    run_id: UUID
    calls: Annotated[list[ToolCall], Field(min_length=1, max_length=8)]


class FinalResponse(StrictModel):
    kind: Literal["final"] = "final"
    run_id: UUID
    report: DraftReport


AgentTurnResponse = ToolCallsResponse | FinalResponse


class WorkspaceTask(StrEnum):
    CHAT = "chat"
    COVER_LETTER = "cover_letter"
    SUMMARIZE = "summarize"
    COMPARE = "compare"
    IMAGE_ANALYSIS = "image_analysis"


class WorkspaceSource(StrictModel):
    source_id: Annotated[str, Field(pattern=r"^[a-zA-Z0-9_.:-]{1,120}$")]
    label: Annotated[str, Field(min_length=1, max_length=255)]
    page: Annotated[int | None, Field(gt=0)] = None
    text: Annotated[str, Field(min_length=1, max_length=20_000)]


class WorkspaceGenerateRequest(StrictModel):
    run_id: UUID
    task: WorkspaceTask
    instruction: Annotated[str, Field(min_length=1, max_length=8_000)]
    sources: Annotated[list[WorkspaceSource], Field(max_length=80)] = Field(default_factory=list)
    image_base64: Annotated[str | None, Field(max_length=14_000_000)] = None

    @model_validator(mode="after")
    def validate_inputs(self) -> "WorkspaceGenerateRequest":
        if self.task in {WorkspaceTask.SUMMARIZE, WorkspaceTask.COMPARE} and not self.sources:
            raise ValueError("document tasks require sources")
        if self.task == WorkspaceTask.IMAGE_ANALYSIS and not self.image_base64:
            raise ValueError("image analysis requires an image")
        return self


class WorkspaceCitation(StrictModel):
    source_id: Annotated[str, Field(min_length=1, max_length=120)]
    page: Annotated[int | None, Field(gt=0)] = None


class WorkspaceGenerateResponse(StrictModel):
    run_id: UUID
    task: WorkspaceTask
    title: Annotated[str, Field(min_length=1, max_length=300)]
    content: Annotated[str, Field(min_length=1, max_length=30_000)]
    citations: Annotated[list[WorkspaceCitation], Field(max_length=100)] = Field(default_factory=list)
    warnings: Annotated[list[str], Field(max_length=30)] = Field(default_factory=list)
