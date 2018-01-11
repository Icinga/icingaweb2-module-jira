-- --
-- WARNING: this is currently unused, it might be introduced with a later
-- version of this module
-- --

CREATE TABLE jira_instance (
  instance_name VARCHAR(64) NOT NULL,
  scheme ENUM('http', 'https') NOT NULL,
  hostname VARCHAR(64) NOT NULL,
  base_url VARCHAR(64) DEFAULT NULL,
  username VARCHAR(64) NOT NULL,
  password VARCHAR(64) NOT NULL,
  PRIMARY KEY(instance_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE jira_template (
  template_name VARCHAR(64) NOT NULL,
  instance_name VARCHAR(64) NOT NULL,
  project_name VARCHAR(64) NOT NULL,
  issue_type_name VARCHAR(64) NOT NULL,
  subject_template VARCHAR(255) NOT NULL,
  description_template TEXT NOT NULL,
  PRIMARY KEY(template_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE jira_template_field (
  template_name VARCHAR(64) NOT NULL,
  field_name VARCHAR(64) NOT NULL,
  field_template VARCHAR(255) NOT NULL,
  PRIMARY KEY (template_name, field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
