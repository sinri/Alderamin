select name, sum(value) as value_sum
from test.t1
group by name