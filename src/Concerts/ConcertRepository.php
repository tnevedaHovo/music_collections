<?php
declare(strict_types=1);

namespace App\Concerts;

use App\Collections\Names;
use App\Core\Db;

/** Consultas e gravação manual da agenda de concertos. */
final class ConcertRepository
{
    public const MY_STATUSES = [
        'interested' => 'Interessa-me',
        'going' => 'Vou',
        'ticket' => 'Tenho bilhete',
    ];

    public const EVENT_STATUSES = [
        'onsale' => 'À venda',
        'offsale' => 'Fora de venda',
        'postponed' => 'Adiado',
        'rescheduled' => 'Nova data',
        'cancelled' => 'Cancelado',
    ];

    /** Campos que o formulário edita; os da fonte marcam o registo como editado. */
    public const SOURCE_FIELDS = ['artist', 'title', 'lineup', 'date', 'time', 'venue', 'city', 'country', 'url', 'price', 'event_status', 'genre'];

    public const SOURCES = ['manual' => 'Adicionado à mão', 'ticketmaster' => 'Ticketmaster', 'bol' => 'BOL'];
    public const PERSONAL_FIELDS = ['my_status', 'notes'];

    /** @return array{rows:array, total:int} */
    public static function list(array $f, int $page, int $perPage = 50): array
    {
        [$where, $params] = self::where($f);
        $past = ($f['when'] ?? '') === 'past';
        $where[] = match ($f['when'] ?? '') {
            'past' => "c.date < date('now', 'localtime')",
            'all' => '1 = 1',
            default => "c.date >= date('now', 'localtime')",
        };

        $sql = ' FROM concerts c WHERE ' . implode(' AND ', $where);
        $order = $past ? 'c.date DESC, c.time DESC' : 'c.date ASC, c.time IS NULL, c.time ASC';
        $offset = max(0, ($page - 1) * $perPage);

        return [
            'total' => (int) Db::value('SELECT COUNT(*)' . $sql, $params),
            'rows' => Db::all('SELECT c.*' . $sql . " ORDER BY $order, c.artist COLLATE NOCASE LIMIT $perPage OFFSET $offset", $params),
        ];
    }

    /**
     * Concertos de um mês (vista de calendário), agrupados por dia. Usa os mesmos filtros
     * que a lista, exceto "próximos/passados".
     * @return array<string, list<array>> 'AAAA-MM-DD' => concertos
     */
    public static function month(array $f, string $ym): array
    {
        [$where, $params] = self::where($f);
        $where[] = 'c.date BETWEEN :from AND :to';
        $params['from'] = $ym . '-01';
        $params['to'] = $ym . '-31';
        $days = [];
        $rows = Db::all('SELECT c.* FROM concerts c WHERE ' . implode(' AND ', $where)
            . ' ORDER BY c.date, c.time IS NULL, c.time, c.artist COLLATE NOCASE', $params);
        foreach ($rows as $r) {
            $days[$r['date']][] = $r;
        }
        return $days;
    }

    /** Condições comuns a todas as vistas da agenda. @return array{0:list<string>, 1:array} */
    private static function where(array $f): array
    {
        $where = ['c.hidden = 0'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(c.artist LIKE :q OR c.title LIKE :q OR c.lineup LIKE :q OR c.venue LIKE :q OR c.city LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (($f['country'] ?? '') !== '') {
            $where[] = 'c.country = :country';
            $params['country'] = $f['country'];
        }
        if (($f['city'] ?? '') !== '') {
            $where[] = 'c.city = :city';
            $params['city'] = $f['city'];
        }
        if (($f['genre'] ?? '') !== '') {
            $where[] = 'c.genre = :genre';
            $params['genre'] = $f['genre'];
        }
        if (isset(self::SOURCES[$f['source'] ?? ''])) {
            $where[] = 'c.source = :source';
            $params['source'] = $f['source'];
        }
        $where[] = match ($f['mine'] ?? '') {
            'any' => 'c.my_status IS NOT NULL',
            'interested', 'going', 'ticket' => 'c.my_status = ' . Db::pdo()->quote($f['mine']),
            'collection' => 'c.artist_id IS NOT NULL',
            default => '1 = 1',
        };
        return [$where, $params];
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM concerts WHERE id = ?', [$id]);
    }

    /** Concerto já na agenda com este link (para avisar de duplicados ao importar). */
    public static function findByUrl(string $url): ?array
    {
        return Db::one('SELECT * FROM concerts WHERE url = ? AND hidden = 0 LIMIT 1', [$url]);
    }

    public static function forArtist(int $artistId, int $limit = 20): array
    {
        return Db::all(
            "SELECT * FROM concerts WHERE artist_id = ? AND hidden = 0 AND date >= date('now', 'localtime')
             ORDER BY date, time LIMIT " . $limit,
            [$artistId]
        );
    }

    /** Países e cidades presentes na agenda (para os filtros). */
    public static function places(): array
    {
        return [
            'countries' => Db::all("SELECT country AS name, COUNT(*) AS n FROM concerts WHERE hidden = 0 AND country <> '' GROUP BY country ORDER BY n DESC"),
            'cities' => Db::all("SELECT city AS name, COUNT(*) AS n FROM concerts WHERE hidden = 0 AND city <> '' GROUP BY city ORDER BY n DESC LIMIT 80"),
            'genres' => Db::all("SELECT genre AS name, COUNT(*) AS n FROM concerts WHERE hidden = 0 AND genre <> '' GROUP BY genre ORDER BY n DESC"),
            'sources' => Db::all('SELECT source AS name, COUNT(*) AS n FROM concerts WHERE hidden = 0 GROUP BY source ORDER BY n DESC'),
        ];
    }

    public static function totals(): array
    {
        return [
            'upcoming' => (int) Db::value("SELECT COUNT(*) FROM concerts WHERE hidden = 0 AND date >= date('now', 'localtime')"),
            'collection' => (int) Db::value("SELECT COUNT(*) FROM concerts WHERE hidden = 0 AND artist_id IS NOT NULL AND date >= date('now', 'localtime')"),
            'mine' => (int) Db::value("SELECT COUNT(*) FROM concerts WHERE hidden = 0 AND my_status IN ('going', 'ticket') AND date >= date('now', 'localtime')"),
        ];
    }

    /**
     * Valida os dados do formulário.
     * @return array{0:array, 1:array} [dados limpos, erros por campo]
     */
    public static function validate(array $in): array
    {
        $d = [];
        foreach ([...self::SOURCE_FIELDS, ...self::PERSONAL_FIELDS] as $k) {
            $v = trim((string) ($in[$k] ?? ''));
            $d[$k] = $v === '' ? null : $v;
        }
        $errors = [];
        if ($d['artist'] === null) {
            $errors['artist'] = 'Indica o artista.';
        }
        if ($d['date'] === null || !self::isDate($d['date'])) {
            $errors['date'] = 'Data inválida.';
        }
        if ($d['time'] !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d['time'])) {
            $errors['time'] = 'Hora inválida (HH:MM).';
        }
        if ($d['url'] !== null && !preg_match('#^https?://#i', $d['url'])) {
            $errors['url'] = 'O endereço tem de começar por http:// ou https://.';
        }
        if ($d['my_status'] !== null && !isset(self::MY_STATUSES[$d['my_status']])) {
            $d['my_status'] = null;
        }
        if ($d['event_status'] !== null && !isset(self::EVENT_STATUSES[$d['event_status']])) {
            $d['event_status'] = null;
        }
        return [$d, $errors];
    }

    /** Cria ($id null) ou atualiza um concerto; devolve o id. */
    public static function save(array $d, ?int $id = null): int
    {
        $d['artist_id'] = self::artistIndex()[TicketmasterClient::key($d['artist'])] ?? null;
        $d['updated_at'] = date('c');

        if ($id === null) {
            $d['source'] = 'manual';
            $d['created_at'] = $d['updated_at'];
            $cols = array_keys($d);
            Db::run(
                'INSERT INTO concerts (' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')',
                $d
            );
            return (int) Db::pdo()->lastInsertId();
        }

        $old = self::find($id);
        // Num importado, mudar um campo da fonte protege-o das sincronizações seguintes.
        foreach (self::SOURCE_FIELDS as $k) {
            if ((string) ($old[$k] ?? '') !== (string) ($d[$k] ?? '')) {
                $d['edited'] = 1;
                break;
            }
        }
        $set = implode(', ', array_map(static fn($k) => "$k = :$k", array_keys($d)));
        Db::run("UPDATE concerts SET $set WHERE id = :id", $d + ['id' => $id]);
        return $id;
    }

    /** Altera só a marcação pessoal (botões rápidos da agenda). */
    public static function setMyStatus(int $id, ?string $status): void
    {
        $status = isset(self::MY_STATUSES[$status ?? '']) ? $status : null;
        Db::run('UPDATE concerts SET my_status = ?, updated_at = ? WHERE id = ?', [$status, date('c'), $id]);
    }

    /** Manuais apagam-se; importados ficam escondidos para não voltarem na próxima sincronização. */
    public static function delete(int $id): void
    {
        Db::run("DELETE FROM concerts WHERE id = ? AND source = 'manual'", [$id]);
        Db::run('UPDATE concerts SET hidden = 1 WHERE id = ?', [$id]);
    }

    /** Volta a permitir que a sincronização atualize um importado editado. */
    public static function revert(int $id): void
    {
        Db::run('UPDATE concerts SET edited = 0 WHERE id = ?', [$id]);
    }

    /** @return array<string,int> nome normalizado → id do artista na coleção */
    public static function artistIndex(): array
    {
        $index = [];
        $rows = Db::all('SELECT id, name, lf_name FROM artists WHERE id NOT IN (' . implode(',', Names::IGNORED_ARTISTS) . ')');
        foreach ($rows as $r) {
            foreach ([$r['name'], $r['lf_name']] as $name) {
                if ($name) {
                    $index[TicketmasterClient::key($name)] ??= (int) $r['id'];
                }
            }
        }
        unset($index['']);
        return $index;
    }

    public static function artistNames(): array
    {
        return Db::all('SELECT a.id, a.name FROM artists a WHERE a.id IN (SELECT artist_id FROM release_artists)
                        AND a.id NOT IN (' . implode(',', Names::IGNORED_ARTISTS) . ') ORDER BY a.name COLLATE NOCASE');
    }

    private static function isDate(string $s): bool
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $s);
        return $d !== false && $d->format('Y-m-d') === $s;
    }
}
