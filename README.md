# Música — plataforma

PHP 8.2 + SQLite, sem dependências. Corre no XAMPP em http://localhost/musica

## Módulo Coleções
- Importa a coleção de um utilizador **Discogs** (API pública; token pessoal opcional para coleções privadas).
- Enriquece cada disco e artista com dados do **Last.fm**: ouvintes, reproduções globais e, com o teu utilizador, as tuas audições.

- Cada disco mostra o **alinhamento** (faixas e durações), obtido do Discogs na primeira visita e guardado em cache.
- **Ouvir**: excertos de 30 s do **Deezer** (API pública, sem chave) em cada faixa e nas capas das grelhas, e faixas completas
  pelos vídeos **YouTube** que o Discogs associa à edição. Um leitor fixo no fundo do ecrã toca o disco seguido.
  Só se guarda o id do álbum no Deezer (`deezer_id`, 0 = não encontrado): os links dos excertos expiram ao fim de algumas horas.

## Módulo Concertos
- Agenda de concertos com adição, edição e remoção manual, e marcação pessoal (interessa-me / vou / tenho bilhete) e notas.
- Importação gratuita da **Ticketmaster Discovery API** (API key em https://developer.ticketmaster.com, 5000 pedidos/dia):
  procura as datas dos artistas da coleção (nos países escolhidos nas Definições) ou a agenda inteira de um país/cidade.
- **BOL** (bol.pt, sem API nem chave): a página de pesquisa "Música & Festivais" lista todos os eventos (~700) com dados
  schema.org e género, e é lida num só pedido. A BOL não indica o artista; os da coleção são reconhecidos quando abrem um
  segmento do título ("Evols + Calcutá", "Sons do Património – emmy Curl"), e tributos são ignorados.
  Por omissão importa só os concertos de artistas da coleção. CLI: `--bol` ou `--bol-all`.
- **Importar de um link** (Songkick, Viral Agenda): no formulário de novo concerto, colar o link de um evento preenche os campos
  a partir dos dados schema.org (JSON-LD) da página. Nenhum dos dois tem API gratuita, por isso é um pedido por evento, feito à mão.
  Na Viral Agenda a hora vem do botão "adicionar ao calendário", porque o JSON-LD deles tem o fuso errado.
- Concertos importados que sejam editados à mão deixam de ser atualizados pela sincronização; os apagados ficam escondidos e não voltam.

## Arranque
1. Abrir **Definições** e preencher o utilizador Discogs e uma API key do Last.fm (gratuita em https://www.last.fm/api/account/create).
2. Em **Sincronizar**, carregar em "Importar coleção" (segue automaticamente para o Last.fm).

Linha de comandos / agendamento: `C:\xampp\php\php.exe sync.php [--discogs] [--lastfm] [--concerts] [--refresh]`

## Estrutura
- `index.php` páginas · `api.php` ações AJAX · `sync.php` CLI
- `src/Core` base de dados, HTTP, definições, vistas
- `src/Collections` clientes Discogs/Last.fm, sincronização, alinhamentos e consultas
- `src/Concerts` cliente Ticketmaster, sincronização e agenda
- `templates/` vistas · `assets/` CSS/JS · `data/` base de dados SQLite (bloqueada ao exterior)

Novos módulos: adicionar `src/<Modulo>`, `templates/<modulo>`, rotas em `index.php` e entrada na barra lateral (`templates/layout.php`).
