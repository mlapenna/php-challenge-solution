INSERT INTO accounts (id, name) VALUES
    (1, 'Acme Marketing'),
    (2, 'Beta Growth');

INSERT INTO visitors (id, account_id, external_id, first_seen_at) VALUES
    (1, 1, 'v_1001', '2026-05-01 08:00:00'),
    (2, 1, 'v_1002', '2026-05-02 09:15:00'),
    (3, 1, 'v_1003', '2026-05-05 14:00:00'),
    (4, 1, 'v_1004', '2026-04-20 10:00:00'),
    (5, 2, 'v_2001', '2026-05-01 11:00:00');

INSERT INTO page_views (visitor_id, path, occurred_at) VALUES
    (1, '/', '2026-05-01 08:00:00'),
    (1, '/pricing', '2026-05-03 10:00:00'),
    (1, '/pricing', '2026-05-04 10:30:00'),
    (1, '/demo', '2026-05-14 12:30:00'),
    (2, '/', '2026-05-02 09:15:00'),
    (2, '/pricing', '2026-05-03 09:20:00'),
    (2, '/blog/visitor-identification', '2026-05-10 16:45:00'),
    (3, '/', '2026-05-05 14:00:00'),
    (3, '/pricing', '2026-05-05 14:05:00'),
    (4, '/pricing', '2026-04-25 10:00:00'),
    (5, '/pricing', '2026-05-12 11:00:00'),
    (5, '/demo', '2026-05-13 11:30:00');

INSERT INTO identity_events (visitor_id, email, company, occurred_at) VALUES
    (1, 'ana@example.com', 'Acme', '2026-05-04 11:00:00'),
    (1, 'ana.silva@example.com', 'Acme', '2026-05-13 08:30:00'),
    (2, 'bruno@example.com', 'Orbit', '2026-05-11 09:00:00'),
    (5, 'carla@example.com', 'Beta', '2026-05-13 12:00:00');

