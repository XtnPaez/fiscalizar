import os
from pathlib import Path

def repo_to_txt(output_file="fiscalizar_completo.txt", max_size_kb=500):
    root_dir = Path(".")
    ignored_dirs = {'.git', 'node_modules', '__pycache__', 'venv', 'env', 'dist', 'build', '.vscode', '.idea'}
    
    with open(output_file, "w", encoding="utf-8") as f:
        f.write("=== ESTRUCTURA DEL REPOSITORIO ===\n\n")
        
        for dirpath, dirnames, filenames in os.walk("."):
            # Ignorar carpetas
            dirnames[:] = [d for d in dirnames if d not in ignored_dirs]
            
            level = dirpath.count(os.sep)
            indent = "    " * level
            folder_name = os.path.basename(dirpath) if dirpath != "." else "fiscalizar"
            f.write(f"{indent}📁 {folder_name}/\n")
            
            for filename in sorted(filenames):
                f.write(f"{indent}    📄 {filename}\n")
        
        f.write("\n" + "="*100 + "\n")
        f.write("=== CONTENIDO DE LOS ARCHIVOS ===\n\n")
        
        for dirpath, dirnames, filenames in os.walk("."):
            dirnames[:] = [d for d in dirnames if d not in ignored_dirs]
            
            for filename in sorted(filenames):
                file_path = Path(dirpath) / filename
                rel_path = file_path.relative_to(root_dir)
                
                # Saltar archivos muy grandes
                if file_path.stat().st_size > max_size_kb * 1024:
                    f.write(f"--- ARCHIVO: {rel_path} --- (demasiado grande, omitido)\n\n")
                    continue
                
                try:
                    with open(file_path, "r", encoding="utf-8") as code:
                        content = code.read()
                    f.write(f"--- ARCHIVO: {rel_path} ---\n")
                    f.write(content)
                    f.write("\n\n" + "-"*80 + "\n\n")
                except UnicodeDecodeError:
                    try:
                        with open(file_path, "r", encoding="latin-1") as code:
                            content = code.read()
                        f.write(f"--- ARCHIVO: {rel_path} --- (encoding latin-1)\n")
                        f.write(content)
                        f.write("\n\n" + "-"*80 + "\n\n")
                    except:
                        f.write(f"--- ARCHIVO: {rel_path} --- (no se pudo leer - binario o encoding desconocido)\n\n")
                except Exception as e:
                    f.write(f"--- ARCHIVO: {rel_path} --- (error: {e})\n\n")
    
    print(f"✅ Listo! Archivo generado: {output_file}")
    print(f"   Tamaño: {Path(output_file).stat().st_size / 1024:.1f} KB")

# Ejecutar
if __name__ == "__main__":
    repo_to_txt()