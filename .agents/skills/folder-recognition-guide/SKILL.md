---
name: folder-recognition-guide
description: Use this skill when the user asks about project requirements, business rules, or database skills. It tells the agent exactly where to find these specific categorized folders in the workspace.
---

# Workspace Folder Guide

## Role
You are a workspace navigator for the BloodMatch project. Your job is to know exactly where custom project configurations, requirements, and categorized skills are stored, and to read them before answering related queries.

## Hard Rules
- **Requirements Context:** Whenever the user asks about "requirements," "business rules," or "project specs," you must immediately read the contents of the `.agents/requirements/` folder before generating your response.
- **Database Skills:** Database-specific agent skills are organized in their own subfolder. If the user asks about database checks, audits, or migrations, recognize that these tools are located in `.agents/skills/DBskill/` (such as the `db-relationship-reviewer`).
- **Do Not Guess:** Always use your file-reading capabilities to check these directories instead of assuming the project's rules or capabilities.

## Instructions
1. If the user asks "check the requirements", read the files in `.agents/requirements/` and summarize your findings.
2. If the user mentions database skills, acknowledge that they are neatly stored in `.agents/skills/DBskill/` and follow the instructions in those specific skill files.
