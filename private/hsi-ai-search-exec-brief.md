# HSI AI Search — Executive Brief
*For IT Review & Executive Approval*

---

## Overview

We have built and deployed (in development) an AI-powered search system for hsi.com that allows visitors to ask natural language questions and receive grounded, sourced answers — without leaving the site. The system uses Retrieval Augmented Generation (RAG), a proven architecture used by enterprise AI products, running on HSI-controlled infrastructure with a clear path to production via AWS.

---

## What's Been Built

The system has two modes accessible from the hsi.com search modal:

- **AI Answer Mode** — The visitor asks a question in plain English. The system retrieves relevant content from our own database (courses, pages, blog/news) and an AI model generates a direct answer with source links.
- **Keyword Mode** — Traditional search powered by our existing Algolia integration.

Content sources currently indexed:
- All primary HSI website pages (crawled and refreshed hourly)
- Full course catalog via Algolia (`prod_new_course_library`) — 5,800+ content chunks
- Blog and news articles via Algolia (`prod_hsi_blog_news`)

All content is refreshed automatically on a weekly schedule (Sundays at 3am).

---

## Privacy & Data Handling

### PII & GDPR

**Current state:** No PII is stored. A user types a question, it is processed in real time, an answer is returned, and nothing is written to a database. Queries are fully ephemeral.

**Production state:** User queries would be transmitted to the production AI provider (AWS Bedrock is the recommended path — see Third-Party AI Providers below) to generate answers. Based on Bedrock's published terms, prompts and responses are not used for model training, but IT SecOps should confirm the applicable DPA and data residency settings as part of their review.

**Query logging for quality evaluation (Phase 2):** If query logging is enabled to build an eval test set, the intent is to store user identifiers as one-way hashed values only — never raw email addresses or names. The proposed approach:

- A server-side salt + SHA-256 hash of the user's identifier stored alongside the query
- Allows grouping queries by user for pattern analysis without storing identifiable information
- GDPR right-to-erasure requests addressed by re-hashing and purging matching rows
- Osano's existing consent management workflow is the likely mechanism to gate query logging — IT and legal should confirm alignment with our current consent scope before this is enabled

**Content being indexed:** All content is public-facing HSI website material (pages, course descriptions, blog posts). No customer data, no employee data, no PII is part of the indexed corpus.

### Third-Party AI Providers

In the current development environment, all AI processing runs locally on HSI infrastructure (Ollama — a local model server). No queries leave our network in dev.

For production, the recommended path is **Anthropic's Claude via AWS Bedrock** — an AWS-native service that would fall under our existing AWS relationship. IT SecOps should confirm the applicable DPA, data residency configuration, and any additional agreements required before production traffic is enabled.

---

## Infrastructure Requirements

### Development (Current)
- Hub server: hub.hsi.com (existing Laravel application)
- Local Ollama model server (development machine only)
- SQLite database for vector embeddings
- Algolia (existing subscription and data agreement)

### Production Requirements (IT Ask)

| Component | Requirement |
|---|---|
| Application server | Existing hub.hsi.com — no change |
| Production LLM | AWS Bedrock — Claude model access enabled in production AWS account. No cost until queries are made. No new vendor — covered under existing AWS agreement. |
| Embedding model | Ollama (`nomic-embed-text`) on a server with ≥8GB RAM, or migrate to AWS Bedrock Titan Embeddings |
| Vector storage | Current: SQLite (sufficient for dev/early production). Future: migrate to pgvector on RDS as query volume grows |
| Algolia | Existing subscription — no change |

**No new SaaS subscriptions are required.** No per-query AI API costs beyond standard AWS Bedrock pricing (pay-per-token, no minimums).

---

## Why Not Microsoft Copilot

Copilot is a Microsoft 365 product built for the Microsoft ecosystem (Teams, Word, Outlook, SharePoint). It does not expose a general-purpose API that can be called from a custom web application backend. It is the wrong tool for powering a search modal on hsi.com.

Anthropic Claude via AWS Bedrock is the correct choice: it is an API-first service, infrastructure already lives on AWS, and it is purpose-built for RAG use cases like this one.

---

## Roadmap

### Phase 1 — Foundation (RAG) ✅ Complete

- Page crawler (`hsi:crawl-pages`) — refreshes hourly
- Vector embeddings (`hsi:embed-pages`) for all HSI pages
- Algolia content embedding (`hsi:embed-algolia`) — courses, blog, news
- AI answer endpoint: `POST /api/hsi/ask`
- Keyword search endpoint: `GET /api/hsi/search`
- Search modal UI live on hsi.com (AI tab + Keyword tab)
- Weekly automated Algolia re-embed (Sundays 3am)
- All processing runs on HSI infrastructure (Ollama) — no data leaves our servers in dev

### Phase 2 — Evals & Quality 🔵 Next

- Define test question set (20–50 questions with known correct answers)
- Automated accuracy scoring — did the right source appear?
- Hallucination detection — did the answer invent facts not present in context?
- Source validation — are cited URLs real and present in retrieved results?
- Regression testing — catch quality drops automatically when content changes
- Quality baseline report — pass/fail threshold required before production launch
- Optional: hashed query logging with Osano consent gate (for real-world eval data)

### Phase 3 — Guardrails ⬜ Planned

- Input filtering — detect and redirect off-topic or harmful queries
- Output validation — block invented URLs and product names
- Brand voice consistency checks
- Escalation path — "I don't know → contact us" fallback for low-confidence answers
- Content safety layer
- **Fallback behavior** — if the model fails or confidence is too low to generate a useful answer, the system degrades gracefully to keyword search results rather than surfacing an error to the visitor

### Phase 4 — Agents ⬜ Future

- Intelligent query routing — system decides which retrieval strategy fits the question type
- Multi-step reasoning — dynamically combine course + page + blog context
- Follow-up question handling — conversational memory within a session
- Potential expansion to internal tools: sales enablement, support, onboarding

### Not on the Roadmap: Fine-Tuning

Fine-tuning (retraining the AI model on HSI-specific data) is **not recommended** for this use case and is not planned. Our content changes frequently — courses are added and updated regularly, blog posts are published weekly. RAG retrieves up-to-date content at query time, which is more accurate and significantly cheaper than baking knowledge into a model's weights. Fine-tuning is better suited for teaching a model a specific style or behavior, not for keeping it current on factual content.

---

## Benefits

- Visitors find courses and answers faster, reducing drop-off and support inquiries
- Works with content we already maintain — no new content authoring required
- No recurring AI API costs in development (local Ollama); predictable pay-per-token in production (AWS Bedrock)
- Privacy-compliant: no PII stored, no training on customer data, existing AWS DPA covers production LLM
- Architecture is extensible to internal tools, sales enablement, and customer support in future phases
- Positions HSI as a modern, AI-forward safety training provider

---

## Open Review Questions for IT

The following items are flagged for IT SecOps and legal to confirm as part of their review. They are not blockers to Phase 2 (Evals), but should be resolved before production launch.

| Topic | Question |
|---|---|
| **Query logging & retention** | If query logging is enabled for eval purposes, what is the appropriate retention period and who owns the deletion schedule? |
| **Data residency** | Which AWS region should Bedrock requests be routed through, and does this align with our current data residency posture? |
| **IAM scoping** | What is the minimum IAM role required for Bedrock access, and should it be scoped to a specific model or usage tier? |
| **Rate limiting** | Should API-level rate limiting be applied to the `/api/hsi/ask` endpoint to prevent abuse or unexpected cost spikes? |
| **Abuse protection** | What controls should be in place to detect and block adversarial prompt injection or misuse of the public-facing search endpoint? |
| **Osano consent scope** | Does our current Osano consent configuration cover AI query logging, or does a new consent category need to be defined? |

---

## Approval Ask

1. **IT review** of infrastructure plan — AWS Bedrock enablement for Claude models in the production account
2. **Security review** of data handling architecture (see Privacy section above)
3. **Sign-off** to proceed to Phase 2 (Evals) in preparation for production launch
4. **AWS console action** — Enable Bedrock model access for Claude in the production AWS account (no cost until live traffic; can be scoped to a specific IAM role)

---

*Prepared by: Hector Ochoa*
*Date: June 2026*
