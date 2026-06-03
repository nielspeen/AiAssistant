# AI Assistant Roadmap

This document is a step by step implementation plan for expanding the AiAssistant module from summarizing and translating into drafting replies with access to internal documentation first, and customer context later.

The plan assumes we only modify the AiAssistant module, not FreeScout core.

## Goals

1. Let admins add support documentation to the AiAssistant module.
2. Index that documentation into searchable chunks.
3. Retrieve relevant documentation for a conversation.
4. Use retrieved documentation as grounded context when drafting replies.
5. Keep the system provider-agnostic where possible.
6. Keep the first version simple enough to ship without external infrastructure.

## Non-Goals For The First Pass

1. No customer data integration yet.
2. No automatic public website crawler yet.
3. No separate vector database requirement yet.
4. No fully autonomous sending of replies.
5. No modification of FreeScout core tables or controllers.

## Phase 1: Agree On The Documentation MVP

Purpose: define the smallest useful documentation source system before writing code.

Decisions to make:

1. Source type for the first version:
   - Decision: admins provide documentation URLs only.
   - The module fetches Markdown by appending `.md` to the documentation URL.
   - Example: `https://example.com/en/docs/article` becomes `https://example.com/en/docs/article.md`.
   - If an admin enters a URL ending in `.md`, normalize the stored public URL by removing `.md`.
   - The fetched Markdown becomes the canonical content to index.
   - Later: pasted text, file upload, help center sync.
   - Added after the MVP: websites can push Markdown directly through a mailbox-authenticated REST API for private or freshly updated docs.

2. Scope:
   - Decision: documentation is mailbox-specific from the start.
   - Each mailbox can have its own documentation set.
   - Later: allow shared/global docs as an optional fallback if useful.

3. Draft behavior:
   - Recommended: draft only, never send automatically.
   - Show retrieved sources beside or under the draft.

4. Citation behavior:
   - Recommended: keep citations visible to the agent, but do not include public source links in the customer reply unless explicitly inserted by the user.

5. Documentation languages:
   - Decision: documentation exists in 4 languages.
   - English is the original/canonical content.
   - The content is equivalent across languages.
   - Supported documentation URL locales: `en`, `ja`, `zh`, `ko`.
   - Public documentation URLs are language-specific, with the locale changing in the URL.
   - Localized URLs are produced by replacing the locale segment in the canonical URL.
   - Recommended MVP: fetch and index the canonical English `.md` URL once, then derive locale-specific public URLs for each article.
   - Retrieval should use the canonical indexed content, while draft replies should choose the public URL that matches the customer/conversation language when possible.

6. Documentation link behavior:
   - Decision: let the AI decide whether to include a documentation link in the draft.
   - The draft should be immediately helpful first, not just a pointer to documentation.
   - The AI should include a localized documentation link when it helps the customer self-serve, verify steps, or learn that documentation exists.
   - The AI should avoid adding a link when the reply is simple, the link would feel like deflection, or retrieval confidence is low.
   - The source link used by the draft should come only from retrieved documentation metadata, never from the model's memory.

Done when:

1. We agree on URL-only documentation sources.
2. We agree how mailbox-specific documentation is selected for a conversation.
3. We agree how language-specific documentation URLs are stored.
4. We agree what the reply draft UI should do at a high level.

## Phase 2: Add Database Tables

Purpose: store documentation and indexed chunks inside the AiAssistant module.

Add migrations for:

### aiassistant_documents

Suggested columns:

1. `id`
2. `mailbox_id`
3. `title`
4. `source_type`
5. `source_url`
6. `source_identifier`
7. `canonical_locale`
8. `localized_urls`
9. `content`
10. `content_hash`
11. `status`
12. `enabled`
13. `last_indexed_at`
14. `last_error`
15. `metadata`
16. `created_at`
17. `updated_at`

Notes:

1. `source_type` can start with `url`.
2. `mailbox_id` associates the document with a FreeScout mailbox.
3. `source_url` should store the canonical English public URL without `.md`.
4. `canonical_locale` should start as `en`.
5. `localized_urls` should store a JSON object of locale to public URL, for example `{"en":"...","ja":"...","zh":"...","ko":"..."}`.
6. `content` should store the fetched canonical English Markdown content for indexing.
7. `content_hash` lets us skip reindexing unchanged content.
8. `last_error` stores the most recent fetch or indexing failure.
9. `metadata` can store future source details as JSON.
10. Do not store `markdown_url`; derive it from `source_url` by appending `.md` when fetching content.

### aiassistant_document_chunks

Suggested columns:

1. `id`
2. `document_id`
3. `chunk_index`
4. `content`
5. `content_hash`
6. `token_count`
7. `embedding`
8. `embedding_model`
9. `metadata`
10. `created_at`
11. `updated_at`

Notes:

1. Store `embedding` as JSON for the MVP.
2. This is not the fastest approach, but it is portable for FreeScout installs.
3. We can later migrate to a vector database or database-native vector column if needed.

Indexes:

1. `documents.mailbox_id`
2. `documents.enabled`
3. `documents.status`
4. `documents.content_hash`
5. `chunks.document_id`
6. `chunks.content_hash`
7. `chunks.embedding_model`

Done when:

1. Module migrations create both tables.
2. Migrations roll back cleanly.
3. No FreeScout core schema is changed.

## Phase 3: Add Documentation Admin UI

Purpose: let an admin manage documentation from the AiAssistant settings area or a module route.

Recommended first UI:

1. Documentation list.
2. Add documentation URL form.
3. Edit documentation URL form.
4. Enable or disable document.
5. Delete document.
6. Reindex document action.

Fields:

1. Mailbox.
2. Canonical English public URL.
3. Enabled.

Optional display:

1. Status.
2. Last indexed time.
3. Chunk count.
4. Last indexing error.
5. Title parsed from Markdown front matter.

Implementation options:

1. Add routes inside `Modules/AiAssistant/Http/routes.php`.
2. Add a lightweight controller under the module.
3. Add Blade views under `Resources/views`.
4. Link from the existing AiAssistant settings page.

Done when:

1. Admin can create, edit, disable, and delete URL-based docs.
2. The module fetches the `.md` URL on save and stores the Markdown title/content.
3. Admin can trigger reindexing.
4. UI stays inside the AiAssistant module.

## Phase 3B: Add Direct Documentation Submission API

Purpose: allow a documentation website or build job to push updated Markdown directly into a mailbox without requiring the content to be publicly fetchable.

API behavior:

1. Admin generates a simple per-mailbox Authorization key in the documentation UI.
2. The module stores only a hash of the key plus a short preview.
3. The raw key is shown once when generated or regenerated.
4. Requests use `Authorization: Bearer <mailbox key>`.
5. The key determines the mailbox. The caller does not submit a mailbox id.
6. The request body includes:
   - `identifier`: stable document id from the website or build system.
   - `content`: Markdown/text content to index.
   - `title`: optional; derive it from Markdown when omitted.
   - `public_url`: optional public canonical URL.
   - `localized_urls`: optional object keyed by `en`, `ja`, `zh`, and `ko`.
   - `canonical_locale`: optional, defaults to `en`.
   - `enabled`: optional, defaults to true.
7. Private documents without a public URL are stored with an internal `api://` source URL and are not shown as clickable links in the UI.
8. New or updated submitted content is chunked and embedded immediately from the submitted payload, without fetching the URL again.
9. If indexing is unavailable because the embedding provider is not configured, the document is still saved and the API response reports the skipped indexing status.
10. If indexing fails after saving, return a JSON error with the provider/indexing detail so automation can alert clearly.

Done when:

1. Admin can generate, regenerate, and revoke a mailbox API key.
2. A website can `POST /ai-assistant/api/documents` with a bearer key and Markdown content.
3. The document is upserted by mailbox plus identifier.
4. The submitted content is indexed immediately without a fetch.
5. Private API documents are visible in the admin list without leaking fake public URLs.

## Phase 4: Add Documentation Indexing Settings

Purpose: configure documentation indexing while using the same AI provider credentials as chat.

Add settings:

1. Embedding model.
2. Chunk size.
3. Chunk overlap.
4. Retrieval result limit.

Recommended defaults:

1. Use "Same as AI Provider" by default for backwards compatibility.
2. Allow a separate documentation embedding provider, API key, base URL, and model.
3. DigitalOcean Serverless Inference can be used for embeddings with `qwen3-embedding-0.6b`.
4. Chunk size: around 800 tokens or 3,000 characters for MVP.
5. Chunk overlap: around 100 tokens or 400 characters for MVP.
6. Retrieval limit: 5 chunks.

Provider capability:

1. The module should know whether the selected documentation embedding provider supports embeddings.
2. If the embedding provider does not support embeddings, disable documentation indexing and retrieval.
3. Translation and summarization should continue to work without documentation features because they use the chat provider.
4. xAI is marked as not supporting embeddings in the module preset. Its chat API works for summaries/translations, but documentation indexing should remain disabled until xAI exposes a reliable embedding model through the OpenAI-compatible embeddings endpoint for the selected account.

Done when:

1. Documentation indexing settings save and load correctly.
2. Embedding requests use the documentation embedding provider base URL and API key, unless "Same as AI Provider" is selected.
3. Documentation features are disabled for embedding providers marked as not supporting embeddings.

## Phase 5: Build The Indexing Service

Purpose: turn documentation content into searchable chunks.

Create services:

1. `DocumentChunker`
2. `EmbeddingService`
3. `DocumentIndexingService`

Chunking behavior:

1. Normalize whitespace.
2. Preserve headings where possible.
3. Split into chunks by headings first.
4. Split oversized sections by paragraph.
5. Add overlap between long chunks.

Embedding behavior:

1. Call OpenAI-compatible `/embeddings`.
2. Send the configured embedding model.
3. Store returned vector as JSON.
4. Store the embedding model name with each chunk.

Indexing behavior:

1. Skip unchanged documents by `content_hash`.
2. Delete old chunks for a changed document.
3. Create new chunks.
4. Embed chunks in batches if the provider supports it.
5. Mark document indexed on success.
6. Store error details on failure.

Done when:

1. A URL-based document can be fetched, converted to Markdown content, and indexed into chunks.
2. Each chunk has an embedding.
3. Reindexing unchanged docs avoids unnecessary API calls.
4. Indexing errors are logged and visible enough to debug.

## Phase 6: Add Indexing Command And Schedule

Purpose: make indexing repeatable and queue-friendly.

Add command:

```bash
php artisan ai-assistant:index-documents
```

Command options:

1. `--document-id=`
2. `--force`
3. `--limit=`

Behavior:

1. Index enabled documents that are new or changed.
2. Respect a limit so cron runs stay small.
3. Print useful command output.
4. Log provider failures.

Scheduling:

1. Add optional scheduled indexing every few minutes.
2. Use `withoutOverlapping`.

Done when:

1. Admin UI can trigger indexing.
2. CLI can index all changed docs.
3. Scheduler can keep docs indexed.

## Phase 7: Build Documentation Retrieval

Purpose: find relevant chunks for a conversation or draft prompt.

Create service:

1. `DocumentSearchService`

Inputs:

1. Search text.
2. Mailbox id from the conversation.
3. Result limit.
4. Minimum similarity threshold.

MVP search algorithm:

1. Embed the search text.
2. Load enabled chunks for the current embedding model.
3. Filter chunks to documents attached to the conversation mailbox.
4. Compute cosine similarity in PHP.
5. Sort descending by score.
6. Return top N chunks.

Result shape:

1. Chunk content.
2. Document title.
3. Document id.
4. Chunk id.
5. Similarity score.
6. Source metadata.
7. Localized public URL for the best matching conversation language, when available.

Done when:

1. Retrieval returns relevant chunks for a sample question. Implemented with `ai-assistant:search-documents`.
2. Disabled documents are ignored.
3. Chunks from old embedding models are ignored or clearly handled.

## Phase 8: Add A Retrieval Test Tool

Purpose: let admins see whether documentation search works before reply drafting exists.

Add UI:

1. Search box.
2. Results list.
3. Score per result.
4. Source document title.
5. Chunk preview.

Why this matters:

1. It makes indexing quality visible.
2. It helps tune chunk size and retrieval limits.
3. It gives us a safe debugging surface.

Done when:

1. Admin can enter a question and see matching documentation chunks.
2. Results include scores and document titles.
3. CLI test command exists now; admin UI can be added after retrieval quality is validated.

## Phase 9: Add Draft Reply Action

Purpose: use retrieved documentation to draft a support reply.

Where to integrate:

1. Conversation view, near reply controls.
2. Start with a button such as "Draft with AI".
3. Do not auto-send.

Draft flow:

1. Collect conversation subject.
2. Collect latest customer message.
3. Include recent thread history or existing conversation summary.
4. Build a retrieval query.
5. Retrieve documentation chunks.
6. Build a grounded prompt.
7. Call the configured chat provider.
8. Return draft text plus the source chunks used.

Prompt rules:

1. Answer as a support agent.
2. Use only provided documentation for product or policy claims.
3. If documentation is insufficient, say what is missing.
4. Do not invent links, policies, prices, or procedures.
5. Keep the tone helpful and concise.
6. Do not reveal internal retrieval metadata to the customer.

Done when:

1. CLI command exists: `ai-assistant:draft-reply {conversation-id}`.
2. Draft is not sent automatically.
3. Output shows which docs were used.
4. Draft fails safely when documentation is missing.
5. Conversation UI button and review panel exist; staff must explicitly insert the generated draft into the reply editor.

## Phase 10: Improve Grounding And Safety

Purpose: reduce hallucinations and make review easier.

Add:

1. Source previews next to draft.
2. "Regenerate" action.
3. "Shorter" and "More detailed" actions.
4. Missing-doc warning when retrieval confidence is low.
5. Optional instruction field for the agent.

Safety checks:

1. Refuse to draft if no relevant docs are found for documentation-dependent questions.
2. Warn when retrieved chunks disagree.
3. Avoid sending sensitive internal notes into the model unless explicitly allowed.

Done when:

1. Drafts are inspectable.
2. Low-confidence drafts are clearly marked.
3. The user remains in control before anything is sent.

## Phase 11: Add More Documentation Sources

Purpose: expand beyond single URL docs after the MVP works.

Potential sources:

1. Sitemap import.
2. Markdown file upload.
3. HTML file upload.
4. Help center API sync.
5. Git repository docs sync.

Recommended order:

1. Sitemap import with domain allowlist.
2. Markdown or HTML upload.
3. External help center sync.
4. Git repository docs sync.

Done when:

1. New sources reuse the same document and chunk tables.
2. Source updates are detected by content hash.
3. Failed imports are visible and retryable.

## Phase 12: Prepare For Customer Context Later

Purpose: design documentation retrieval so customer data can be added without rewrites.

Future context categories:

1. Customer profile.
2. Recent customer conversations.
3. Subscription or account status.
4. Order or billing details.
5. Product usage metadata.

Important separation:

1. Documentation context is general product knowledge.
2. Customer context is private and conversation-specific.
3. The prompt should label these sections separately.
4. Customer context should never be stored in documentation chunks.

Future prompt structure:

1. System instructions.
2. Conversation context.
3. Retrieved documentation.
4. Customer/account context.
5. Drafting instructions.

Done when:

1. Draft service accepts context blocks by type.
2. Documentation retrieval does not assume it is the only context source.

## Phase 13: Consider A Real Vector Database

Purpose: scale search if the module outgrows JSON embeddings in MySQL/MariaDB.

Stay with DB plus PHP similarity while:

1. Chunk count is small or medium.
2. Search latency is acceptable.
3. Installation simplicity matters most.

Consider vector infrastructure when:

1. There are tens of thousands of chunks.
2. Retrieval becomes slow.
3. Multiple mailboxes need separate large corpora.
4. Ranking quality needs hybrid search.

Options:

1. Qdrant.
2. Weaviate.
3. Pinecone.
4. pgvector, if the installation already uses Postgres.
5. MySQL vector support, if available in the deployed version.

Done when:

1. We have measured retrieval latency.
2. We know the expected documentation size.
3. We decide whether portability or scale matters more.

## Open Questions

1. Should agents be able to override the selected documentation locale before inserting a link?
2. Which embedding provider and default model should we recommend?
3. How large is the expected documentation corpus per mailbox?
4. Should indexing run synchronously from the UI or only through a command/schedule?
5. Should internal notes be included when drafting replies?
6. Should AI drafts be stored in the database for audit history?

## Suggested First Work Item

Start with Phase 1 and choose the documentation MVP:

1. URL-only documentation sources.
2. Mailbox-specific documentation.
3. Encrypted database settings for embedding credentials.
4. Fetched canonical English Markdown content with locale-specific public URLs.
5. Module DB tables with JSON embeddings.
6. Admin retrieval test UI before draft replies.

This gives us a small, testable foundation before touching the conversation reply workflow.
