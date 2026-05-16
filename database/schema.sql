DROP TABLE IF EXISTS identity_events;
DROP TABLE IF EXISTS page_views;
DROP TABLE IF EXISTS visitors;
DROP TABLE IF EXISTS accounts;

CREATE TABLE accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE visitors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    external_id VARCHAR(80) NOT NULL,
    first_seen_at DATETIME NOT NULL,
    CONSTRAINT fk_visitors_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    UNIQUE KEY visitors_account_external_unique (account_id, external_id),
    KEY visitors_account_idx (account_id)
);

CREATE TABLE page_views (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    visitor_id BIGINT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL,
    occurred_at DATETIME NOT NULL,
    CONSTRAINT fk_page_views_visitor FOREIGN KEY (visitor_id) REFERENCES visitors(id),
    KEY page_views_visitor_time_idx (visitor_id, occurred_at),
    KEY page_views_path_time_idx (path, occurred_at)
);

CREATE TABLE identity_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    visitor_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NULL,
    company VARCHAR(120) NULL,
    occurred_at DATETIME NOT NULL,
    CONSTRAINT fk_identity_events_visitor FOREIGN KEY (visitor_id) REFERENCES visitors(id),
    KEY identity_events_visitor_time_idx (visitor_id, occurred_at)
);

