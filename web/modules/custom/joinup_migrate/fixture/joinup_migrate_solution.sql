CREATE OR REPLACE VIEW joinup_migrate_solution (
  collection,
  type,
  nid,
  vid,
  title,
  created_time,
  changed_time,
  uri,
  landing_page,
  docs_fid,
  docs_filepath,
  docs_timestamp,
  docs_uid,
  sid,
  policy,
  policy2,
  banner,
  logo,
  body,
  metrics_page
) AS
SELECT
  m.collection,
  m.type,
  m.nid,
  n.vid,
  n.title,
  FROM_UNIXTIME(n.created, '%Y-%m-%dT%H:%i:%s'),
  FROM_UNIXTIME(n.changed, '%Y-%m-%dT%H:%i:%s'),
  TRIM(uri.field_id_uri_value),
  TRIM(ctd.field_documentation_access_url1_url),
  f.fid,
  f.filepath,
  f.timestamp,
  f.uid,
  w.sid,
  m.policy,
  m.policy2,
  m.banner,
  m.logo,
  nr.body,
  TRIM(cfu.field_id_uri_value)
FROM joinup_migrate_mapping m
INNER JOIN joinup_migrate_prepare p ON m.collection = p.collection
INNER JOIN node n ON m.nid = n.nid
INNER JOIN node_revisions nr ON n.vid = nr.vid
LEFT JOIN og_ancestry o ON m.nid = o.nid
LEFT JOIN node g ON o.group_nid = g.nid AND g.type = 'repository'
LEFT JOIN content_field_id_uri uri ON n.vid = uri.vid
LEFT JOIN content_type_asset_release car ON n.vid = car.vid
LEFT JOIN node d ON car.field_asset_homepage_doc_nid = d.nid
LEFT JOIN content_type_documentation ctd ON d.vid = ctd.vid
LEFT JOIN files f ON ctd.field_documentation_access_url_fid = f.fid
LEFT JOIN workflow_node w ON m.nid = w.nid
LEFT JOIN content_field_asset_sw_metrics swm ON n.vid = swm.vid
LEFT JOIN node nm ON swm.field_asset_sw_metrics_nid = nm.nid
LEFT JOIN content_field_id_uri cfu ON nm.vid = cfu.vid
WHERE m.type IN('asset_release', 'project_project')
AND m.migrate = 1
AND (
  (m.type = 'asset_release' AND g.type = 'repository')
  OR
  m.type = 'project_project'
)
ORDER BY m.collection ASC, m.nid ASC
