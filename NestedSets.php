<?php
/**
 * Created by PhpStorm.
 * User: nazi
 * Date: 06.09.18
 * Time: 2:48
 */

class NestedSets
{

    protected $table;
    protected $db;

    protected function db()
    {
        try {
            $db = new PDO('mysql:host=localhost;dbname=tree;charset=utf8',
                'admin', '1488');
        } catch (PDOException $exception) {
            die($exception->getMessage());
        }

        return $db;
    }

    public function __construct($table)
    {
        $this->table = $table;
        $this->db = $this->db();
    }

    // create tree
    public function create($title = NULL)
    {
        $this->db->exec('DELETE FROM ' . $this->table);
        $this->db->exec('ALTER TABLE ' . $this->table . ' AUTO_INCREMENT=0');

        $sql = '
			INSERT 
			INTO ' . $this->table . ' 
			VALUES(NULL, 1, 2, 1, :title)';

        $statement = $this->db->prepare($sql);
        $statement->bindParam(':title', $title);
        $statement->execute();
    }

    // add node
    public function add($id, $title = NULL)
    {
        $node = $this->get($id);
        $right_key = $node['right_key'];
        $lvl = $node['lvl'];

        $sql_update = '
			UPDATE ' . $this->table . ' 
			SET 
				right_key = right_key + 2, 
				left_key = IF(left_key > ' . $right_key . ', left_key + 2, left_key) 
			WHERE right_key >= ' . $right_key;
        $this->db->exec($sql_update);

        $sql_insert = '
			INSERT 
			INTO ' . $this->table . ' 
			SET 
				left_key = ' . $right_key . ', 
				right_key = ' . $right_key . ' + 1, lvl = ' . $lvl . ' + 1, 
				title = :title';

        $statement = $this->db->prepare($sql_insert);
        $statement->bindParam(':title', $title);
        $statement->execute();
        return $this->db->lastInsertId();
    }

    // delete node
    public function del($id)
    {
        $node = $this->get($id);
        $left_key = $node['left_key'];
        $right_key = $node['right_key'];

        $sql_delete = '
			DELETE 
			FROM ' . $this->table . ' 
			WHERE 
				left_key >= ' . $left_key . ' AND 
				right_key <= ' . $right_key;

        $sql_update = '
			UPDATE ' . $this->table . ' 
			SET 
				left_key = IF(left_key > ' . $left_key . ', left_key - (' . $right_key . ' - ' . $left_key . ' + 1), left_key), 
				right_key = right_key - (' . $right_key . ' - ' . $left_key . ' + 1) 
			WHERE right_key > ' . $right_key;

        $this->db->exec($sql_delete);
        $this->db->exec($sql_update);
    }

    // move to other node
    public function move($id, $id_to)
    {
        $node = $this->get($id);
        $node_parent = self::parent_node($id);

        $node_to = $this->get($id_to);

        // sort not in this context
        if ($node_parent['id'] == $node_to['id']) {
            echo '==\n';
            return FALSE;
        }
        // move to root TODO
        if (!$id_to) {
            echo '0\n';
            return FALSE;
        }

        $left_key = $node['left_key'];
        $right_key = $node['right_key'];
        $lvl = $node['lvl'];

        $lvl_up = $node_to['lvl'];

        $statement = $this->db->query('SELECT (right_key - 1) AS right_key FROM ' . $this->table . ' WHERE id = ' . $id_to);

        $right_key_near = $statement->fetch(PDO::FETCH_ASSOC)['right_key'];

        $skew_lvl = $lvl_up - $lvl + 1;
        $skew_tree = $right_key - $left_key + 1;

        $statement = $this->db->query('SELECT id FROM ' . $this->table . ' WHERE left_key >= ' . $left_key . ' AND right_key <= ' . $right_key);

        $id_edit = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC))
            $id_edit[] = $row['id'];

        $id_edit = implode(', ', $id_edit);

        if ($right_key_near < $right_key) {
            //вышестоящие
            $skew_edit = $right_key_near - $left_key + 1;
            $sql[0] = '
				UPDATE ' . $this->table . ' 
				SET right_key = right_key + ' . $skew_tree . ' 
				WHERE 
					right_key < ' . $left_key . ' AND 
					right_key > ' . $right_key_near;
            $sql[1] = '
				UPDATE ' . $this->table . ' 
				SET left_key = left_key + ' . $skew_tree . ' 
				WHERE 
					left_key < ' . $left_key . ' AND 
					left_key > ' . $right_key_near;
            $sql[2] = '
				UPDATE ' . $this->table . ' 
				SET left_key = left_key + ' . $skew_edit . ', 
					right_key = right_key + ' . $skew_edit . ', 
					lvl = lvl + ' . $skew_lvl . ' 
				WHERE id IN (' . $id_edit . ')';

        } else {
            //нижестоящие
            $skew_edit = $right_key_near - $left_key + 1 - $skew_tree;

            $sql[0] = '
				UPDATE ' . $this->table . ' 
				SET right_key = right_key - ' . $skew_tree . ' 
				WHERE 
					right_key > ' . $right_key . ' AND 
					right_key <= ' . $right_key_near;

            $sql[1] = '
				UPDATE ' . $this->table . ' 
				SET left_key = left_key - ' . $skew_tree . ' 
				WHERE 
					left_key > ' . $right_key . ' AND 
					left_key <= ' . $right_key_near;

            $sql[2] = '
				UPDATE ' . $this->table . ' 
				SET left_key = left_key + ' . $skew_edit . ', 
					right_key = right_key + ' . $skew_edit . ', 
					lvl = lvl + ' . $skew_lvl . ' 
				WHERE id IN (' . $id_edit . ')';
        }
        $this->db->exec($sql[0]);
        $this->db->exec($sql[1]);
        $this->db->exec($sql[2]);
    }

    // GETTERS

    // get node
    public function get($id)
    {
        $sql = '
			SELECT * 
			FROM ' . $this->table . ' 
			WHERE id = :id';

        $statement = $this->db->prepare($sql);
        $statement->bindParam(':id', $id);
        $statement->execute();
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    // tree
    public function tree($parent_node = TRUE)
    {
        if ($parent_node) {
            $sql = '
			SELECT id, title, lvl 
			FROM ' . $this->table . ' 
			ORDER BY left_key';
        } else {
            $sql = '
			SELECT id, title, lvl 
			FROM ' . $this->table . ' 
			WHERE id != 1
			ORDER BY left_key';
        }

        $statement = $this->db->query($sql);
        $r = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC))
            $r[$row['id']] = $row;
        return $r;
    }

    // child branch
    public function child_branch($id, $parent_node = TRUE)
    {
        $node = $this->get($id);
        $left_key = $node['$left_key'];
        $right_key = $node['roght_key'];

        if ($parent_node) {
            $sql = '
			SELECT id, title, lvl 
			FROM ' . $this->table . ' 
			WHERE 
				left_key >= ' . $left_key . ' AND 
				right_key <= ' . $right_key . ' 
			ORDER BY left_key';
        } else {
            $sql = '
			SELECT 
				id, title, lvl 
			FROM 
				' . $this->table . ' 
			WHERE 
				left_key >= ' . $left_key . ' 
				AND 
				right_key <= ' . $right_key . ' 
				AND
				id != :id
			ORDER BY 
				left_key';
        }

        $statement = $this->db->prepare($sql);
        if (!$parent_node)
            $statement->bindParam(':id', $id);
        $statement->execute();

        $r = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC))
            $r[$row['id']] = $row;
        return $r;
    }

    // children
    public function child($id, $parent_node = TRUE)
    {
        $node = $this->get($id);
        $left_key = $node['left_key'];
        $right_key = $node['right_key'];
        $lvl = $node['lvl'] + 1;

        if ($parent_node) {
            $sql = '
			SELECT id, title, lvl 
			FROM ' . $this->table . ' 
			WHERE 
				left_key >= ' . $left_key . ' AND 
				right_key <= ' . $right_key . ' AND
				lvl <= ' . $lvl . '
			ORDER BY left_key';
        } else {
            $sql = '
			SELECT id, title, lvl 
			FROM ' . $this->table . ' 
			WHERE 
				left_key >= ' . $left_key . ' AND 
				right_key <= ' . $right_key . ' AND
				lvl <= ' . $lvl . ' AND
				id != :id
			ORDER BY left_key';
        }

        $statement = $this->db->prepare($sql);
        if (!$parent_node)
            $statement->bindParam(':id', $id);
        $statement->execute();
        $r = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC))
            $r[$row['id']] = $row;
        return $r;
    }

    // parent branch
    public function parent_branch($id)
    {
        $node = $this->get($id);
        $left_key = $node['left_key'];
        $right_key = $node['right_key'];

        $sql = '
			SELECT id, title, lvl 
			FROM ' . $this->table . ' 
			WHERE 
				left_key <= ' . $left_key . ' AND 
				right_key >= ' . $right_key . ' 
			ORDER BY left_key';

        $statement = $this->db->query($sql);
        $r = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC))
            $r[$row['id']] = $row;
        return $r;
    }

    // parenr node
    public function parent_node($id)
    {
        $node = $this->get($id);
        $left_key = $node['left_key'];
        $right_key = $node['right_key'];
        $lvl = $node['lvl'] - 1;
        $sql = '
			SELECT id, title, lvl 
			FROM ' . $this->table . ' 
			WHERE 
				left_key <= ' . $left_key . ' AND 
				right_key >= ' . $right_key . ' AND
				lvl = ' . $lvl . '
			ORDER BY left_key';

        $statement = $this->db->query($sql);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    // branch
    public function branch($id)
    {
        $node = $this->get($id);
        $left_key = $node['left_key'];
        $right_key = $node['right_key'];
        $sql = '
			SELECT id, title, lvl 
			FROM ' . $this->table . ' 
			WHERE 
				right_key > ' . $left_key . ' AND 
				left_key < ' . $right_key . ' 
			ORDER BY left_key';

        $statement = $this->db->query($sql);
        $r = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC))
            $r[$row['id']] = $row;
        return $r;
    }
}
