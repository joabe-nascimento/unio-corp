#!/bin/bash
# Remove arquivos órfãos após renomeação via git mv
# Este script é executado no servidor durante o deploy

echo ""
echo "==> Remover arquivos órfãos (renomeações git mv)"

ORPHANED_FILES=(
    "src/Controller/Api/VitoriaApiController.php"
    "src/Service/Vitoria"
    "src/Command/VitoriaSmokeTestCommand.php"
    "src/Service/PosOperatorio/VitoriaContextService.php"
)

for file in "${ORPHANED_FILES[@]}"; do
    if [ -e "$file" ]; then
        echo "  🗑️  Removendo: $file"
        rm -rf "$file"
    fi
done

echo "  ✅ Limpeza de arquivos órfãos concluída"
echo ""
