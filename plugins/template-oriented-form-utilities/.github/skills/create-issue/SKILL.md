---
name: create-issue
description: 'Create a new issue markdown file in the issues/ directory. Use when tracking a bug, feature request, or task for the TOFU plugin. The file name is auto-generated from the current date/time as YYYY-mm-dd-hh-ii-ss.md.'
argument-hint: 'Brief title or description of the issue'
---

# Create Issue

## When to Use

- Recording a new bug, feature request, or implementation task
- Documenting a planned change with background, goals, and detailed tasks
- Creating a traceable, timestamped artifact for a piece of work

## Procedure

1. **Get the current date/time** — run `date '+%Y-%m-%d-%H-%M-%S'` in the terminal to produce the filename timestamp (format: `YYYY-mm-dd-hh-ii-ss`).

2. **Determine the filename** — `issues/<timestamp>.md`

3. **Gather information** — ask the user (or infer from context):
   - Issue type: Bug | Feature | Task | Refactor
   - Title (concise, action-oriented)
   - Overview / problem description
   - Background / existing infrastructure (if relevant)
   - Goals (numbered list)
   - Detailed tasks (with code blocks / pseudo-code where helpful)

4. **Create the file** using the template below, substituting values.

5. **Confirm** — report the created file path to the user.

---

## File Template

```markdown
# <Type>: <Title>

**Date:** YYYY-MM-DD
**Status:** Open

---

## Overview

<A few sentences describing the problem or feature and why it matters.>

---

## Background

<Relevant existing infrastructure, constraints, or prior decisions. Use a table when listing components.>

| Component | Location | Current state |
|---|---|---|
| … | … | … |

---

## Goals

1. <Goal one>
2. <Goal two>
3. …

---

## Detailed Tasks

### 1. <Task title>

<Description, acceptance criteria, and code snippets where helpful.>

### 2. <Task title>

…
```

### Status values

| Value | Meaning |
|---|---|
| `Open` | Not yet started |
| `In Progress` | Actively being worked on |
| `Implemented` | Code merged; may add `**Updated:**` line |
| `Closed` | Resolved without implementation (e.g. won't fix) |

### Type values

`Bug` · `Feature` · `Task` · `Refactor` · `Chore`
