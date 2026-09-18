-- ============================================================
-- REMOVE DEMO / TEST DATA FROM A LIVE INSTALL
-- ============================================================
--
-- Run this ONLY when you are certain the rows it targets are test data.
-- Unlike config/migrate.sql this is DESTRUCTIVE and is NOT idempotent in a
-- meaningful sense — once a venture is gone, it is gone.
--
-- BEFORE RUNNING:
--   phpMyAdmin -> select the database -> Export -> Go
--   Keep that .sql file. It is the only way back.
--
-- HOW TO USE:
--   1. Run STEP 1 on its own. It only SELECTs — it changes nothing, and shows
--      you exactly what STEP 3 would delete.
--   2. Read that list. If anything on it is real, stop and tell your developer.
--   3. Edit the venture ids in STEP 2 to match what you actually want removed.
--   4. Run STEP 3.
--
-- WHY THE IDS ARE NOT PRE-FILLED: a venture id on your live site does not match
-- a venture id here, and deleting id 21 because that was the test venture on a
-- developer machine is exactly how real data disappears.
-- ============================================================


-- ── STEP 1 — LOOK FIRST (safe, read-only) ───────────────────
-- Every closed/cancelled venture, with what would go with it.

SELECT
  v.id,
  v.title,
  v.founder_name,
  v.status,
  v.cancelled_at,
  v.cancelled_by,
  (SELECT COUNT(*) FROM venture_members m  WHERE m.venture_id = v.id) AS members,
  (SELECT COUNT(*) FROM transactions t
     WHERE t.venture_id = v.id AND t.type = 'refund_request') AS refund_rows,
  (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t
     WHERE t.venture_id = v.id AND t.type = 'refund_request'
       AND t.status = 'pending') AS money_still_owed
FROM ventures v
WHERE v.status = 'cancelled'
ORDER BY v.cancelled_at DESC;

-- Anything with money_still_owed > 0 is a real person waiting to be paid.
-- Deleting it does not cancel the obligation, it just hides it.


-- ── STEP 2 — NAME THE VENTURES TO REMOVE ────────────────────
-- Replace the ids below with the ones from STEP 1. Keep the parentheses.

SET @doomed = '21,22';   -- <== EDIT THIS


-- ── STEP 3 — DELETE ─────────────────────────────────────────
-- Transactions first: transactions.venture_id is ON DELETE SET NULL, so
-- deleting the venture alone would leave orphaned money rows behind with the
-- venture name still on them, cluttering the Refund Requests queue forever.
-- Everything else (members, applications, meetups, chat, media, documents,
-- reports) is removed by ON DELETE CASCADE when the venture row goes.

DELETE t FROM transactions t
 WHERE FIND_IN_SET(t.venture_id, @doomed);

DELETE n FROM notifications n
 JOIN ventures v ON FIND_IN_SET(v.id, @doomed)
 WHERE n.message LIKE CONCAT('%', v.title, '%');

DELETE FROM ventures
 WHERE FIND_IN_SET(id, @doomed);


-- ── STEP 4 — CHECK ──────────────────────────────────────────

SELECT
  (SELECT COUNT(*) FROM ventures)                                       AS ventures_left,
  (SELECT COUNT(*) FROM ventures WHERE status = 'cancelled')            AS closed_left,
  (SELECT COUNT(*) FROM transactions
     WHERE type = 'refund_request' AND status = 'pending')              AS pending_refunds,
  (SELECT COUNT(*) FROM transactions WHERE venture_id IS NULL)          AS orphaned_money_rows;

SELECT 'Cleanup complete.' AS result;
