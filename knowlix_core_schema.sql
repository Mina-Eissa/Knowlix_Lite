-- Knowlix Lite — Core Service database schema
-- MySQL 8, InnoDB, utf8mb4
-- Order matters: each table only references tables created above it.

CREATE DATABASE IF NOT EXISTS knowlix_core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE knowlix_core;

-- ---------------------------------------------------------------
-- 1. workspaces — the tenant boundary. Every other table hangs off this.
-- ---------------------------------------------------------------
CREATE TABLE workspaces (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) NOT NULL,
    settings      JSON NULL,
    created_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_workspaces_slug (slug)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 2. users — email is unique per workspace, not globally
-- ---------------------------------------------------------------
CREATE TABLE users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id  BIGINT UNSIGNED NOT NULL,
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('admin','agent','member') NOT NULL,
    created_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_users_workspace_email (workspace_id, email),
    KEY idx_users_workspace (workspace_id),
    CONSTRAINT fk_users_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 3. categories — one level of nesting max (enforced in app logic,
--    not the schema; MySQL can't easily constrain "max depth 1")
-- ---------------------------------------------------------------
CREATE TABLE categories (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id  BIGINT UNSIGNED NOT NULL,
    parent_id     BIGINT UNSIGNED NULL,
    name          VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_categories_workspace_slug (workspace_id, slug),
    KEY idx_categories_workspace (workspace_id),
    KEY idx_categories_parent (parent_id),
    CONSTRAINT fk_categories_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE CASCADE,
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id)
        REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 4. articles — slug unique per workspace; soft-deleted on archive
-- ---------------------------------------------------------------
CREATE TABLE articles (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id  BIGINT UNSIGNED NOT NULL,
    category_id   BIGINT UNSIGNED NOT NULL,
    author_id     BIGINT UNSIGNED NOT NULL,
    title         VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) NOT NULL,
    body          LONGTEXT NOT NULL,
    status        ENUM('draft','in_review','published') NOT NULL DEFAULT 'draft',
    published_at  DATETIME NULL,
    created_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    deleted_at    TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_articles_workspace_slug (workspace_id, slug),
    KEY idx_articles_workspace (workspace_id),
    KEY idx_articles_category (category_id),
    KEY idx_articles_author (author_id),
    KEY idx_articles_status (status),
    CONSTRAINT fk_articles_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE CASCADE,
    CONSTRAINT fk_articles_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_articles_author FOREIGN KEY (author_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 5. article_versions — immutable snapshots, one row per publish
-- ---------------------------------------------------------------
CREATE TABLE article_versions (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id    BIGINT UNSIGNED NOT NULL,
    version       INT UNSIGNED NOT NULL,
    body          LONGTEXT NOT NULL,
    editor_id     BIGINT UNSIGNED NOT NULL,
    created_at    TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_article_versions_article_version (article_id, version),
    KEY idx_article_versions_article (article_id),
    KEY idx_article_versions_editor (editor_id),
    CONSTRAINT fk_article_versions_article FOREIGN KEY (article_id)
        REFERENCES articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_versions_editor FOREIGN KEY (editor_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 6. tickets
-- ---------------------------------------------------------------
CREATE TABLE tickets (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id  BIGINT UNSIGNED NOT NULL,
    requester_id  BIGINT UNSIGNED NOT NULL,
    assignee_id   BIGINT UNSIGNED NULL,
    subject       VARCHAR(255) NOT NULL,
    body          LONGTEXT NOT NULL,
    status        ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
    priority      ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    created_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    KEY idx_tickets_workspace (workspace_id),
    KEY idx_tickets_requester (requester_id),
    KEY idx_tickets_assignee (assignee_id),
    KEY idx_tickets_status (status),
    KEY idx_tickets_priority (priority),
    CONSTRAINT fk_tickets_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_requester FOREIGN KEY (requester_id)
        REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_assignee FOREIGN KEY (assignee_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 7. ticket_comments — is_internal rows never emit events (app-layer rule)
-- ---------------------------------------------------------------
CREATE TABLE ticket_comments (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id     BIGINT UNSIGNED NOT NULL,
    author_id     BIGINT UNSIGNED NOT NULL,
    body          LONGTEXT NOT NULL,
    is_internal   TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    KEY idx_ticket_comments_ticket (ticket_id),
    KEY idx_ticket_comments_author (author_id),
    CONSTRAINT fk_ticket_comments_ticket FOREIGN KEY (ticket_id)
        REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_comments_author FOREIGN KEY (author_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 8. tags — name unique per workspace
-- ---------------------------------------------------------------
CREATE TABLE tags (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id  BIGINT UNSIGNED NOT NULL,
    name          VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_tags_workspace_name (workspace_id, name),
    KEY idx_tags_workspace (workspace_id),
    CONSTRAINT fk_tags_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 9. taggables — polymorphic pivot shared by articles and tickets
-- ---------------------------------------------------------------
CREATE TABLE taggables (
    tag_id          BIGINT UNSIGNED NOT NULL,
    taggable_id     BIGINT UNSIGNED NOT NULL,
    taggable_type   VARCHAR(255) NOT NULL,
    PRIMARY KEY (tag_id, taggable_id, taggable_type),
    KEY idx_taggables_morph (taggable_id, taggable_type),
    CONSTRAINT fk_taggables_tag FOREIGN KEY (tag_id)
        REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 10. attachments — polymorphic, files live outside the web root
-- ---------------------------------------------------------------
CREATE TABLE attachments (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attachable_id     BIGINT UNSIGNED NOT NULL,
    attachable_type   VARCHAR(255) NOT NULL,
    uploader_id       BIGINT UNSIGNED NOT NULL,
    path              VARCHAR(255) NOT NULL,
    original_name     VARCHAR(255) NOT NULL,
    mime              VARCHAR(100) NOT NULL,
    size              BIGINT UNSIGNED NOT NULL,
    created_at        TIMESTAMP NULL DEFAULT NULL,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    KEY idx_attachments_morph (attachable_id, attachable_type),
    KEY idx_attachments_uploader (uploader_id),
    CONSTRAINT fk_attachments_uploader FOREIGN KEY (uploader_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 11. webhook_events — the outbox; event_id (ULID) never changes once written
-- ---------------------------------------------------------------
CREATE TABLE webhook_events (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id          CHAR(26) NOT NULL,
    type              VARCHAR(100) NOT NULL,
    workspace_id      BIGINT UNSIGNED NOT NULL,
    payload           JSON NOT NULL,
    status            ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
    attempts          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at   TIMESTAMP NULL DEFAULT NULL,
    delivered_at      TIMESTAMP NULL DEFAULT NULL,
    last_error        TEXT NULL,
    created_at        TIMESTAMP NULL DEFAULT NULL,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_webhook_events_event_id (event_id),
    KEY idx_webhook_events_type (type),
    KEY idx_webhook_events_status (status),
    KEY idx_webhook_events_workspace (workspace_id),
    CONSTRAINT fk_webhook_events_workspace FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id) ON DELETE CASCADE
) ENGINE=InnoDB;
