<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD . 'gmap/libraries/DataSource.php';

class Data_import_model extends CI_Model {
	
	public function __construct()
	{
		parent::__construct();
	}
		
	public function start_import($id)
	{
		$sql = '
		UPDATE exp_gmap_import_stats 
		SET 
			importer_last_ran = '.$this->localize->now.',
			importer_total_runs = importer_total_runs + 1
		WHERE
			schema_id = '.$id;
		
		$this->db->query($sql);
			
		return $this->get_stats($id);
	}
	
	public function import_success($id)
	{
		$sql = '
		UPDATE exp_gmap_import_stats 
		SET 
			total_entries_imported = total_entries_imported + 1
		WHERE
			schema_id = '.$id;
		
		$this->db->query($sql);
		
		return $this->get_stats($id);
	}
	
	public function import_failed($id)
	{
		$sql = '
		UPDATE exp_gmap_import_stats 
		SET 
			total_entries_failed = total_entries_failed + 1
		WHERE
			schema_id = '.$id;
		
		$this->db->query($sql);
		
		return $this->get_stats($id);
	}
	
	/*------------------------------------------
	 *	Settings
	/* -------------------------------------- */
	
	
	public function get_setting($id)
	{
		$setting = $this->get_settings($id);
		
		if($setting->num_rows() == 0)
		{
			show_error($id . ' is not a valid setting id');
		}
		
		return json_decode($setting->row('settings'));
	}
	
	public function get_settings($schema_id = FALSE)
	{
		if($schema_id)
		{
			$this->db->where('schema_id', $schema_id);
		}
		
		return $this->db->get('gmap_import_settings');
	}
	
	public function save_settings($schema_id, $settings)
	{
		$existing = $this->db->where('schema_id', $schema_id)->get('gmap_import_settings')->num_rows() > 0 ? TRUE : FALSE;
		
		if(!$existing)
		{
			$this->db->insert('gmap_import_settings', array(
				'settings'   => $settings
			));
			
			$schema_id = $this->db->insert_id();
			
			$this->db->insert('gmap_import_stats', array(
				'schema_id'              => $schema_id, 
				'schema_name'            => json_decode($settings)->id, 
				'total_entries_imported' => 0,
				'total_entries_failed'   => 0,
				'importer_last_ran'      => 0,
				'importer_total_runs'    => 0,
			));
		}
		else
		{
			$this->db->where('schema_id', $schema_id);
			$this->db->update('gmap_import_settings', array(
				'settings'   => $settings
			));
		}
	}
	
	/*------------------------------------------
	 *	Stats
	/* -------------------------------------- */
		
	public function get_stats($id = FALSE, $status = 'pending')
	{
		$where = NULL;
		
		if($id)
		{
			$where = ' AND stats.schema_id = \''.$id.'\'';
		}
		
		$sql = 'SELECT 
			pool.schema_id, 
			stats.schema_name,
			count(id) as \'items_in_pool\',
			stats.total_entries_imported,
			stats.total_entries_failed,
			stats.importer_last_ran,
			stats.importer_total_runs
		FROM 
			exp_gmap_import_pool as `pool`
		LEFT JOIN
			exp_gmap_import_stats as `stats`
		ON
			pool.schema_id = stats.schema_id
		WHERE
			'.($status ? 'pool.status = \''.$status.'\'' : NULL).'
		'.$where.'
		GROUP BY 
			pool.schema_id';
		
		if(!$id)
		{	
			return $this->db->query($sql);
		}
		else
		{
			return $this->db->query($sql)->row();
		}
	}
	
	public function reset_stat_count($id)
	{
		$this->db->where('schema_id', $id);
		$this->db->update('gmap_import_stats', array(
			'total_entries_imported' => 0,
			'importer_total_runs'    => 0
		));
	}
	
	/*------------------------------------------
	 *	Pool
	/* -------------------------------------- */
		
	public function get_pools($id = FALSE, $status = 'pending')
	{
		if($id)
		{
			$this->db->where('schema_id', $id);
		}
		
		if($status)
		{
			$this->db->where('status', $status);	
		}
		
		return $this->db->get('gmap_import_pool');
	}
	
	public function get_item($id)
	{
		return $this->get_pools($id)->row();
	}
	
	public function delete_pool($id = FALSE)
	{
		if($id)
		{
			$this->db->where('id', $id);
		}
		
		$this->db->delete('gmap_import_pool');
	}
	
}