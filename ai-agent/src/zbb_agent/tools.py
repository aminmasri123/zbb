from __future__ import annotations

from .schemas import ToolName


_DESCRIPTIONS: dict[ToolName, str] = {
    ToolName.PARTICIPANT_IDENTITY: "Load the minimal authorized participant identity summary.",
    ToolName.LUV_DATA: "Load authorized LuV facts for the immutable reporting period.",
    ToolName.ATTENDANCE: "Load an authorized attendance summary calculated by Laravel.",
    ToolName.DOCUMENTATION: "Load authorized documentation entries for the reporting period.",
    ToolName.GOALS: "Load authorized goals and documented progress for the reporting period.",
    ToolName.PROJECT_RULES: "Load the approved project rules valid for the reporting period.",
    ToolName.TEMPLATE: "Load metadata for the approved report template valid for the period.",
}


def ollama_tools(allowed_tools: set[ToolName]) -> list[dict[str, object]]:
    return [
        {
            "type": "function",
            "function": {
                "name": name.value,
                "description": _DESCRIPTIONS[name],
                "parameters": {
                    "type": "object",
                    "properties": {},
                    "additionalProperties": False,
                },
            },
        }
        for name in sorted(allowed_tools, key=lambda item: item.value)
    ]
