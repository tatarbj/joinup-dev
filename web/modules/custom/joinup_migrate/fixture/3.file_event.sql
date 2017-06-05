CREATE OR REPLACE VIEW d8_file_event (
  fid,
  path,
  timestamp,
  uid,
  destination_uri
) AS
SELECT
  f.fid,
  SUBSTRING(f.filepath, 21),
  f.timestamp,
  f.uid,
  CONCAT('public://event/attachment/', SUBSTRING_INDEX(f.filepath, '/', -1))
FROM content_field_event_documentation cfed
INNER JOIN files f ON cfed.field_event_documentation_fid = f.fid
INNER JOIN node n ON cfed.vid = n.vid
INNER JOIN d8_event e ON n.nid = e.nid
