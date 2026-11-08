-- Prevent the same recruitment candidate from being hired more than once
-- within a tenant. Multiple NULL candidate_id values remain permitted.

ALTER TABLE `hr_employees`
    ADD UNIQUE KEY `uq_hr_emp_tenant_candidate` (`tenant_id`, `candidate_id`);
