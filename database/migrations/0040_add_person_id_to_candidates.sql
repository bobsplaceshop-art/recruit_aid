-- =====================================================================
-- 0040_add_person_id_to_candidates.sql
-- Links a recruit candidate to its canonical core_persons row.
--
-- Nullable by design: existing candidates predate core_persons. The
-- backfill script bin/backfill-candidate-persons.php links any rows
-- that lack a person_id (no-op today; recruit_candidates is empty).
-- Batch 28 (PersonService) populates person_id on every new create.
--
-- ON DELETE SET NULL: deleting a person must not delete the candidate
-- record (candidate may still be needed for audit / applications).
-- =====================================================================

ALTER TABLE `recruit_candidates`
    ADD COLUMN `person_id` INT UNSIGNED NULL AFTER `id`,
    ADD KEY `idx_candidate_person` (`person_id`),
    ADD CONSTRAINT `fk_rec_cand_person`
        FOREIGN KEY (`person_id`) REFERENCES `core_persons` (`id`)
        ON DELETE SET NULL;
