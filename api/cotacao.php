<?php
// api/cotacao.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config.php';
require_once '../session.php';

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'POST':
        // Enviar vistoria para cotação
        $data = json_decode(file_get_contents('php://input'), true);
        $vistoriaId = $data['vistoria_id'] ?? 0;
        
        // Verificar se vistoria existe e tem arquivo anexado
        $stmt = $pdo->prepare("SELECT * FROM vistorias WHERE id = ? AND status = 'Concluída' AND arquivo_lista_seguro IS NOT NULL");
        $stmt->execute([$vistoriaId]);
        $vistoria = $stmt->fetch();
        
        if (!$vistoria) {
            http_response_code(400);
            echo json_encode(['error' => 'Vistoria não encontrada, não está concluída ou não possui arquivo anexado']);
            exit;
        }
        
        // Verificar permissão
        if (!isGestor() && $vistoria['vendedor'] !== $_SESSION['usuario_nome']) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão para enviar esta vistoria para cotação']);
            exit;
        }
        
        // Verificar se já existe cotação para esta vistoria
        $stmt = $pdo->prepare("SELECT id FROM cotacoes WHERE vistoria_id = ?");
        $stmt->execute([$vistoriaId]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Esta vistoria já foi enviada para cotação']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Criar registro de cotação
            $stmt = $pdo->prepare("INSERT INTO cotacoes (vistoria_id, status) VALUES (?, 'Aguardando_Parceiros')");
            $stmt->execute([$vistoriaId]);
            $cotacaoId = $pdo->lastInsertId();
            
            // Atualizar status da vistoria
            $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Enviada_Cotacao' WHERE id = ?");
            $stmt->execute([$vistoriaId]);
            
            // Registrar histórico
            $stmt = $pdo->prepare("INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                                  VALUES ('vistorias', ?, 'Concluída', 'Enviada_Cotacao', ?, 'Vistoria enviada para cotação')");
            $stmt->execute([$vistoriaId, $_SESSION['usuario_id']]);
            
            // Criar notificação para responsáveis por cotação
            $stmt = $pdo->query("SELECT id, nome FROM usuarios WHERE tipo = 'cotador' AND ativo = 1");
            $cotadores = $stmt->fetchAll();
            
            foreach ($cotadores as $cotador) {
                $stmt = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem) 
                                      VALUES (?, 'nova_cotacao', 'Nova Vistoria para Cotação', ?)");
                $mensagem = "A vistoria #{$vistoriaId} do cliente {$vistoria['cliente']} foi enviada para cotação.";
                $stmt->execute([$cotador['id'], $mensagem]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'cotacao_id' => $cotacaoId,
                'message' => 'Vistoria enviada para cotação com sucesso'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao enviar vistoria para cotação: ' . $e->getMessage()]);
        }
        break;
        
    case 'GET':
        // Obter status da cotação
        if (!isset($_GET['vistoria_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da vistoria não fornecido']);
            exit;
        }
        
        $vistoriaId = $_GET['vistoria_id'];
        
        // Buscar cotação
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(DISTINCT cp.id) as total_parceiros,
                   COUNT(DISTINCT CASE WHEN cp.valor IS NOT NULL THEN cp.id END) as parceiros_responderam,
                   MIN(cp.valor) as menor_valor,
                   MAX(cp.valor) as maior_valor
            FROM cotacoes c
            LEFT JOIN cotacoes_parceiros cp ON c.id = cp.cotacao_id
            WHERE c.vistoria_id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$vistoriaId]);
        $cotacao = $stmt->fetch();
        
        if ($cotacao) {
            // Buscar detalhes das cotações dos parceiros
            $stmt = $pdo->prepare("
                SELECT cp.*, p.nome as parceiro_nome
                FROM cotacoes_parceiros cp
                JOIN parceiros p ON cp.parceiro_id = p.id
                WHERE cp.cotacao_id = ?
                ORDER BY cp.valor ASC
            ");
            $stmt->execute([$cotacao['id']]);
            $cotacao['parceiros'] = $stmt->fetchAll();
        }
        
        echo json_encode($cotacao ?: null);
        break;
        
    case 'PUT':
        // Atualizar cotação (aprovar valor)
        if (!isGestor()) {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas gestores podem aprovar cotações']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $cotacaoId = $data['cotacao_id'] ?? 0;
        $valorAprovado = $data['valor_aprovado'] ?? 0;
        
        try {
            $pdo->beginTransaction();
            
            // Buscar informações da cotação
            $stmt = $pdo->prepare("SELECT vistoria_id FROM cotacoes WHERE id = ?");
            $stmt->execute([$cotacaoId]);
            $cotacao = $stmt->fetch();
            
            if (!$cotacao) {
                throw new Exception('Cotação não encontrada');
            }
            
            // Atualizar cotação
            $stmt = $pdo->prepare("UPDATE cotacoes SET status = 'Aprovada', valor_aprovado = ?, data_aprovacao = NOW() WHERE id = ?");
            $stmt->execute([$valorAprovado, $cotacaoId]);
            
            // Atualizar status da vistoria
            $stmt = $pdo->prepare("UPDATE vistorias SET status = 'Cotacao_Aprovada' WHERE id = ?");
            $stmt->execute([$cotacao['vistoria_id']]);
            
            // Registrar histórico
            $stmt = $pdo->prepare("INSERT INTO historico_status (tabela, registro_id, status_anterior, status_novo, usuario_id, observacoes) 
                                  VALUES ('vistorias', ?, 'Enviada_Cotacao', 'Cotacao_Aprovada', ?, ?)");
            $stmt->execute([$cotacao['vistoria_id'], $_SESSION['usuario_id'], 'Cotação aprovada com valor: R$ ' . number_format($valorAprovado, 2, ',', '.')]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Cotação aprovada com sucesso']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao aprovar cotação: ' . $e->getMessage()]);
        }
        break;
}
?>