from bcrypt import hashpw, gensalt

# Password to hash
password = "THEFT.remains.cold".encode("utf-8")

# Generate bcrypt hash
hashed_password = hashpw(password, gensalt()).decode("utf-8")
print("Hashed Password:", hashed_password)
