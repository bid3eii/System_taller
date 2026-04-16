import re

def check_php_tags(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Find PHP blocks
    php_blocks = re.findall(r'<\?php(.*?)\?>', content, re.DOTALL)
    
    stack = []
    
    # Simple regex for if/else/endif/foreach/endforeach colons
    # capturing the line number would be better but this is a start
    
    # We need to process the whole file and find tags
    lines = content.splitlines()
    for i, line in enumerate(lines, 1):
        # Look for the start of alternative syntax
        # Pattern: if (...): or else: or elseif (...): or foreach (...):
        
        # Matches: <?php if (...): ?> or inside a block: if (...):
        # This is simplified but should find the keywords
        
        # Check for opens
        if re.search(r'if\s*\(.*?\)\s*:', line) or re.search(r'else\s*:', line) or re.search(r'elseif\s*\(.*?\)\s*:', line) or re.search(r'foreach\s*\(.*?\)\s*:', line):
            # If it's an else or elseif, it doesn't add to stack depth, but it checks if stack is empty
            if 'else' in line:
                if not stack:
                    print(f"Error: orphaned 'else' on line {i}")
            else:
                stack.append(('open', i, line.strip()))
        
        # Check for closes
        if 'endif;' in line:
            if not stack:
                print(f"Error: orphaned 'endif' on line {i}")
            else:
                stack.pop()
        
        if 'endforeach;' in line:
            if not stack:
                print(f"Error: orphaned 'endforeach' on line {i}")
            else:
                stack.pop()

    if stack:
        print("Unclosed blocks found:")
        for s in stack:
            print(f"Line {s[1]}: {s[2]}")
    else:
        print("All alternative syntax blocks seem balanced (basic check).")

check_php_tags(r'c:\xampp\htdocs\System_taller\modules\equipment\entry.php')
