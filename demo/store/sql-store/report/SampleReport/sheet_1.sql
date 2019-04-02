select `name`, `value_sum`
from `test`.`t2`
where `value_sum` > #{limitation}
order by `value_sum`
limit 10