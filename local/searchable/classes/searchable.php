<?php

namespace local_searchable;

use stdClass;

class searchable {

	static function set_weight($objecttype, $objectid, $tag, $weight) {

		global $DB;

		$tagrecord = $DB->get_record('searchable_tags', ['tag' => $tag]);
		if ($tagrecord == null) {
			$tagrecord = new stdClass;
			$tagrecord->tag = $tag;
			$id = $DB->insert_record('searchable_tags', $tagrecord);
			$tagrecord->id = $id;
		}

		$record = $DB->get_record('searchable_objects', ['objecttype' => $objecttype, 'objectid' => $objectid, 'tagid' => $tagrecord->id]);
		if ($record == null) {
			$record = new stdClass;
			$record->objecttype = $objecttype;
			$record->objectid = $objectid;
			$record->tagid = $tagrecord->id;
			$record->weight = $weight;
			$DB->insert_record('searchable_objects', $record);
		} else {
			$record->weight = $weight;
			$DB->update_record('searchable_objects', $record);
		}

	}

	static function set_weights($objecttype, $objectid, $weights) {
		global $DB;

		$currentweights = $DB->get_records('searchable_objects', ['objecttype' => $objecttype, 'objectid' => $objectid]);

		$tagids = array_map(function($i) { return $i->tagid; }, $currentweights);
		$currenttags = $DB->get_records_list('searchable_tags', 'id', $tagids);

		$todelete = [];
		foreach($currentweights as $record) {

			$tag = $currenttags[$record->tagid];

			if (empty($weights[$tag])) {
				$todelete[] = $record->id;
				continue;
			}

			$newweight = $weights[$tag];
			if ($newweight == $record->weight) {
				continue;
			}

			$record->weight = $newweight;

			$DB->update_record('searchable_objects', $record);

			unset($weights[$tag]);
		}

		$DB->delete_records_list('searchable_objects', 'id', $todelete);

		foreach($weights as $tag => $weight) {
			self::set_weight($objecttype, $objectid, $tag, $weight);
		}

	}

	static function remove_object($objecttype, $objectid) {
		$DB->delete_records('searchable_objects', ['objecttype' => $objecttype, 'objectid' => $objectid]);
	}

	/**
	 * Get searchable results.
	 * @param string $objecttype The type of objects to search
	 * @param [string] $query The tags to search for
	 * @param bool $like Use "like" search. Obviously a lot more costly.
	 * @param int $limit The maximum number of objects to return.
	 * @param int $offset The database offset, for paging.
	 * @return [stdClass] The searchable records matching your search query.
	 */
	static function results($objecttype, $query, $like = false, $limit = 20, $offset = 0) {
		global $DB;

		if (count($query) == 0) {
			return [];
		}

		if ($like) {
			$sql = []; $params = [];
			foreach($query as $term) {
				if (strlen($term) < 2) {
					//ignore short terms
					continue;
				}
				$sql[] = $DB->sql_like('tag', '?');
				$params[] = '%' . $DB->sql_like_escape($term) . '%';
			}

			//no searchable tags, no results
			if (count($sql) == 0) {
				return [];
			}

			$tags = $DB->get_records_select('searchable_tags', implode($sql, ' OR '), $params);

		} else {

			list($sql, $params) = $DB->get_in_or_equal($query);
			$tags = $DB->get_records_select('searchable_tags', "tag $sql", $params);

		}

		if (count($tags) == 0) {
			return [];
		}

		list($sql, $params) = $DB->get_in_or_equal(array_keys($tags), SQL_PARAMS_NAMED);
		$params['objecttype'] = $objecttype;
		$rslt = $DB->get_records_select('searchable_objects', "objecttype = :objecttype AND tagid $sql", $params, 'weight DESC', 'id, objectid, tagid, weight', $offset, $limit);

		$results = [];
		foreach($rslt as $r) {
			$tag = $tags[$r->tagid]->tag;
			if(empty($results[$r->objectid])) {
				$r->tags = [$tag];
				unset($r->tagid);
				unset($r->id);
				$results[$r->objectid] = $r;
			} else {
				$results[$r->objectid]->weight += $r->weight;
				$results[$r->objectid]->tags[] = $tag;
			}
		}

		usort($results, function($a, $b) {
			if ($a->weight > $b->weight) {
				return -1;
			}
			if ($a->weight < $b->weight) {
				return 1;
			}
			return 0;
		});

		return $results;

	}

}